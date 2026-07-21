<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const ACTIVE_GUARD_OPEN = 'OPEN';

    public const OPERATION_TIMEZONE = 'America/Tegucigalpa';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'declared_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeCurrentlyOpen(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_OPEN)
            ->where('active_guard', self::ACTIVE_GUARD_OPEN);
    }

    public function isOverdue(): bool
    {
        return $this->opened_at
            ->setTimezone(self::OPERATION_TIMEZONE)
            ->isBefore(now(self::OPERATION_TIMEZONE)->startOfDay());
    }
}
