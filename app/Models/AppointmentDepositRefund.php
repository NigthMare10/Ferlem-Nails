<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AppointmentDepositRefund extends Model
{
    public const PURPOSE_TERMINAL = 'terminal';

    public const PURPOSE_EXCESS = 'excess';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las devoluciones de adelantos son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las devoluciones de adelantos son inmutables.'));
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(AppointmentDeposit::class, 'appointment_deposit_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
