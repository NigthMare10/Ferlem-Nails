<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\BuildExpensesListAction;
use App\Actions\Expenses\CancelExpenseAction;
use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Http\Requests\CancelExpenseRequest;
use App\Http\Requests\ExpensesIndexRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseDetailResource;
use App\Http\Resources\ExpenseListResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\ExpenseAttachmentStorage;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(ExpensesIndexRequest $request, BuildExpensesListAction $action): Response
    {
        $filters = $request->validated();
        unset($filters['page']);
        return Inertia::render('Expenses/Index', [
            'expenses' => fn () => ExpenseListResource::collection($action->execute($request->user(), $filters)),
            'filters' => $filters,
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'employees' => User::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'capabilities' => [
                'create' => $request->user()->can(Permissions::EXPENSES_CREATE),
                'manage_categories' => $request->user()->can(Permissions::EXPENSES_MANAGE_CATEGORIES),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request, CreateExpenseAction $action): RedirectResponse
    {
        $data = $request->validated();
        unset($data['attachment']);
        $expense = $action->execute($request->user(), $data, $request->file('attachment'));

        return redirect()->route('expenses.show', $expense)->with('success', 'El gasto fue registrado correctamente.');
    }

    public function show(Request $request, Expense $expense): Response
    {
        abort_unless($request->user()->can(Permissions::EXPENSES_ACCESS) && $request->user()->can(Permissions::EXPENSES_VIEW), 403);
        abort_unless(Expense::query()->visibleTo($request->user())->whereKey($expense)->exists(), 403);
        $expense->load([
            'category:id,name', 'employee:id,name', 'recordedBy:id,name', 'canceledBy:id,name',
            'events.performedBy:id,name', 'payrollObligation:id,expense_id',
        ]);

        return Inertia::render('Expenses/Show', [
            'expense' => (new ExpenseDetailResource($expense))->resolve($request),
            'categories' => ExpenseCategory::query()->where(function ($query) use ($expense): void {
                $query->where('is_active', true)->orWhere('id', $expense->category_id);
            })->orderBy('name')->get(['id', 'name']),
            'employees' => User::query()->where(function ($query) use ($expense): void {
                $query->where('is_active', true)->when($expense->employee_id, fn ($query, $id) => $query->orWhere('id', $id));
            })->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpenseAction $action): RedirectResponse
    {
        abort_unless(Expense::query()->visibleTo($request->user())->whereKey($expense)->exists(), 403);
        $action->execute($request->user(), $expense, $request->validated());

        return back(303)->with('success', 'El gasto fue modificado correctamente.');
    }

    public function cancel(CancelExpenseRequest $request, Expense $expense, CancelExpenseAction $action): RedirectResponse
    {
        abort_unless(Expense::query()->visibleTo($request->user())->whereKey($expense)->exists(), 403);
        $action->execute($request->user(), $expense, $request->string('cancellation_reason')->toString());

        return back(303)->with('success', 'El gasto fue anulado correctamente.');
    }

    public function attachment(Request $request, Expense $expense): StreamedResponse
    {
        abort_unless($request->user()->can(Permissions::EXPENSES_VIEW_ATTACHMENT), 403);
        abort_unless(Expense::query()->visibleTo($request->user())->whereKey($expense)->exists(), 403);
        abort_unless($expense->attachment_path
            && preg_match('#^\d{4}/\d{2}/[a-f0-9]{48}\.(jpg|png|webp|pdf)$#', $expense->attachment_path), 404);
        $disk = Storage::disk(ExpenseAttachmentStorage::DISK);
        abort_unless($disk->exists($expense->attachment_path), 404);
        $stream = $disk->readStream($expense->attachment_path);
        abort_if($stream === false, 404);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $expense->attachment_original_name ?: "comprobante-{$expense->expense_number}",
            "comprobante-{$expense->expense_number}",
        );

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $expense->attachment_mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

}
