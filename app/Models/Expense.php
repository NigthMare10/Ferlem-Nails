<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Expense extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CANCELED = 'canceled';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'attachment_size' => 'integer',
            'attachment_uploaded_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Expense $expense) {
            if ($expense->isDirty('expense_number') && $expense->getOriginal('expense_number') !== null) {
                throw new LogicException('El número de gasto es inmutable.');
            }
            if ($expense->getOriginal('status') === self::STATUS_CANCELED
                && $expense->status !== self::STATUS_CANCELED) {
                throw new LogicException('Un gasto anulado no puede reactivarse.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los gastos no pueden eliminarse físicamente.'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function attachmentUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attachment_uploaded_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExpenseEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function payrollObligation(): HasOne
    {
        return $this->hasOne(PayrollObligation::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Permissions::PAYROLL_VIEW)) {
            return $query;
        }

        return $query->whereDoesntHave('payrollObligation');
    }

    public function isPayrollExpense(): bool
    {
        return $this->payrollObligation()->exists();
    }
}
