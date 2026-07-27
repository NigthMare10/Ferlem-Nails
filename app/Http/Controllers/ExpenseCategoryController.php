<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryStatusRequest;
use App\Models\ExpenseCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Expenses/Categories', [
            'categories' => ExpenseCategory::query()
                ->withCount('expenses')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_active'])
                ->map(fn (ExpenseCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'is_active' => $category->is_active,
                    'expenses_count' => $category->expenses_count,
                ]),
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();
        $slug = $this->availableSlug($name);
        try {
            ExpenseCategory::query()->create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con un nombre equivalente.']);
        }

        return back(303)->with('success', 'La categoría fue creada correctamente.');
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $name = $request->string('name')->toString();
        try {
            $expenseCategory->update(['name' => $name, 'slug' => $this->availableSlug($name, $expenseCategory)]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con un nombre equivalente.']);
        }

        return back(303)->with('success', 'La categoría fue modificada correctamente.');
    }

    public function status(UpdateExpenseCategoryStatusRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update(['is_active' => $request->boolean('is_active')]);

        return back(303)->with('success', $expenseCategory->is_active ? 'La categoría fue activada.' : 'La categoría fue desactivada.');
    }

    private function availableSlug(string $name, ?ExpenseCategory $category = null): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'El nombre debe contener letras o números.']);
        }
        $exists = ExpenseCategory::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con un nombre equivalente.']);
        }

        return $slug;
    }
}
