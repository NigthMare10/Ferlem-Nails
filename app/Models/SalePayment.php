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
            'proof_size' => 'integer',
            'proof_uploaded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SalePayment $payment) {
            $proofFields = [
                'proof_path', 'proof_original_name', 'proof_mime', 'proof_size',
                'proof_uploaded_by', 'proof_uploaded_at', 'updated_at',
            ];
            if ($payment->method === Sale::PAYMENT_METHOD_TRANSFER
                && $payment->getOriginal('proof_path') === null
                && $payment->proof_path !== null
                && array_diff(array_keys($payment->getDirty()), $proofFields) === []) {
                return;
            }

            throw new LogicException('Los pagos de una venta son inmutables.');
        });
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
