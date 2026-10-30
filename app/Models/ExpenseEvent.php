<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ExpenseEvent extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_CANCELED = 'canceled';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'previous_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La auditoría de gastos es inmutable.'));
        static::deleting(fn () => throw new LogicException('La auditoría de gastos no puede eliminarse.'));
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
