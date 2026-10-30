<?php

namespace App\Actions\Expenses;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEvent;
use App\Models\User;
use App\Support\ExpenseAttachmentStorage;
use App\Support\ExpenseAudit;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateExpenseAction
{
    public function __construct(
        private readonly ExpenseAttachmentStorage $attachmentStorage,
        private readonly PublishInternalNotificationAction $publishNotification,
    ) {}

    public function execute(User $user, array $data, ?UploadedFile $file = null): Expense
    {
        if (! $user->is_active || ! $user->can(Permissions::EXPENSES_ACCESS) || ! $user->can(Permissions::EXPENSES_CREATE)) {
            throw new AuthorizationException;
        }

        $data['amount'] = Money::fromCents(Money::toCents((string) $data['amount']));
        $requestHash = $this->requestHash($data, $file);

        if ($existing = $this->findByToken($data['checkout_token'])) {
            return $this->resolveExisting($existing, $user, $requestHash);
        }

        $attachment = $file ? $this->storeAttachment($file, $user) : null;

        try {
            $expense = DB::transaction(function () use ($user, $data, $requestHash, $attachment) {
                if ($existing = $this->findByToken($data['checkout_token'])) {
                    return $this->resolveExisting($existing, $user, $requestHash);
                }

                $category = ExpenseCategory::query()->lockForUpdate()->findOrFail($data['category_id']);
                if (! $category->is_active) {
                    throw ValidationException::withMessages(['category_id' => 'La categoría seleccionada está inactiva.']);
                }
                $employee = isset($data['employee_id'])
                    ? User::query()->lockForUpdate()->findOrFail($data['employee_id'])
                    : null;
                if ($employee && ! $employee->is_active) {
                    throw ValidationException::withMessages(['employee_id' => 'El empleado relacionado debe estar activo.']);
                }

                $expense = new Expense;
                $expense->expense_number = null;
                $expense->checkout_token = $data['checkout_token'];
                $expense->request_hash = $requestHash;
                $expense->category_id = $category->getKey();
                $expense->category_name_snapshot = $category->name;
                $expense->expense_date = $data['expense_date'];
                $expense->description = $data['description'];
                $expense->amount = $data['amount'];
                $expense->payment_method = $data['payment_method'];
                $expense->vendor = $data['vendor'] ?? null;
                $expense->employee_id = $employee?->getKey();
                $expense->status = Expense::STATUS_RECORDED;
                $expense->notes = $data['notes'] ?? null;
                $expense->recorded_by = $user->getKey();
                foreach ($attachment ?? [] as $field => $value) {
                    $expense->{$field} = $value;
                }
                $expense->save();
                $expense->expense_number = 'GA-'.str_pad((string) $expense->getKey(), 6, '0', STR_PAD_LEFT);
                $expense->save();
                $expense->setRelation('employee', $employee);

                $event = new ExpenseEvent;
                $event->expense_id = $expense->getKey();
                $event->type = ExpenseEvent::TYPE_CREATED;
                $event->performed_by = $user->getKey();
                $event->occurred_at = now('UTC');
                $event->new_values = ExpenseAudit::values($expense);
                $event->save();

                DB::afterCommit(function () use ($event, $expense, $user): void {
                    try {
                        $this->publishNotification->execute(
                            $user,
                            'expense.created',
                            'Gasto registrado',
                            "Se registró el gasto {$expense->expense_number}.",
                            "/expenses/{$expense->getKey()}",
                            ['type' => 'expense', 'id' => $expense->getKey()],
                            "expense-created:{$expense->getKey()}",
                            $event->occurred_at,
                            Permissions::EXPENSES_VIEW,
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });

                return $expense;
            }, 3);

            if ($attachment && $expense->attachment_path !== $attachment['attachment_path']) {
                $this->attachmentStorage->delete($attachment);
            }

            return $this->load($expense);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isCheckoutTokenConflict($exception)) {
                $this->attachmentStorage->delete($attachment);
                throw $exception;
            }
            $existing = $this->findByToken($data['checkout_token']);
            if (! $existing) {
                $this->attachmentStorage->delete($attachment);
                throw $exception;
            }
            $this->attachmentStorage->delete($attachment);

            return $this->resolveExisting($existing, $user, $requestHash);
        } catch (Throwable $exception) {
            $this->attachmentStorage->delete($attachment);
            throw $exception;
        }
    }

    private function requestHash(array $data, ?UploadedFile $file): string
    {
        unset($data['checkout_token'], $data['attachment']);
        ksort($data);
        $data['attachment_sha256'] = $file ? hash_file('sha256', $file->getRealPath()) : null;

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function findByToken(string $token): ?Expense
    {
        return Expense::query()->useWritePdo()->where('checkout_token', $token)->first();
    }

    private function resolveExisting(Expense $expense, User $user, string $requestHash): Expense
    {
        if ($expense->recorded_by !== $user->getKey()) {
            throw new AuthorizationException;
        }
        if (! hash_equals($expense->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Esta confirmación ya fue utilizada para otro gasto. Abre nuevamente el formulario.',
            ]);
        }

        return $this->load($expense);
    }

    private function storeAttachment(UploadedFile $file, User $user): array
    {
        try {
            return $this->attachmentStorage->store($file, $user);
        } catch (Throwable) {
            throw ValidationException::withMessages(['attachment' => 'No se pudo guardar el comprobante. Inténtalo nuevamente.']);
        }
    }

    private function isCheckoutTokenConflict(UniqueConstraintViolationException $exception): bool
    {
        $driver = DB::connection($exception->getConnectionName())->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $exception->index === 'expenses_checkout_token_unique',
            'sqlite' => $exception->columns === ['checkout_token'],
            default => false,
        };
    }

    private function load(Expense $expense): Expense
    {
        return $expense->loadMissing(['category:id,name', 'employee:id,name', 'recordedBy:id,name']);
    }
}
