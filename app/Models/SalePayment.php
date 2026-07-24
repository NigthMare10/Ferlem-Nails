<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SalePayment extends Model
{
    public const TYPE_DEPOSIT_APPLIED = 'deposit_applied';

    public const TYPE_FINAL_PAYMENT = 'final_payment';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'card_fee_rate' => 'decimal:2',
            'card_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los pagos de una venta son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los pagos de una venta no pueden eliminarse.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function appointmentDeposit(): BelongsTo
    {
        return $this->belongsTo(AppointmentDeposit::class);
    }
}
