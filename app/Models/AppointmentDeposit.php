<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class AppointmentDeposit extends Model
{
    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const CARD_FEE_RATE = '4.00';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_RETAINED = 'retained';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'card_fee_rate' => 'decimal:2',
            'card_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'retained_amount' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AppointmentDeposit $deposit) {
            $immutable = ['appointment_id', 'amount', 'payment_method', 'card_fee_rate', 'card_fee_amount', 'net_amount', 'paid_at', 'recorded_by'];
            if ($deposit->isDirty($immutable)) {
                throw new LogicException('Los datos originales del adelanto son inmutables.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los adelantos no pueden eliminarse.'));
    }

    public static function feeSnapshot(string $amount, string $paymentMethod): array
    {
        $amountCents = Money::toCents($amount);
        $feeRate = $paymentMethod === self::PAYMENT_METHOD_CARD ? self::CARD_FEE_RATE : '0.00';
        $feeCents = $paymentMethod === self::PAYMENT_METHOD_CARD
            ? Money::percentageOfCents($amountCents, $feeRate)
            : 0;

        return [
            'amount' => Money::fromCents($amountCents),
            'card_fee_rate' => $feeRate,
            'card_fee_amount' => Money::fromCents($feeCents),
            'net_amount' => Money::fromCents($amountCents - $feeCents),
        ];
    }

    public function availableAmountCents(): int
    {
        return max(0, Money::toCents($this->amount)
            - Money::toCents($this->refunded_amount)
            - Money::toCents($this->retained_amount)
            - Money::toCents($this->applied_amount));
    }

    public function availableAmount(): string
    {
        return Money::fromCents($this->availableAmountCents());
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(AppointmentDepositRefund::class)->orderBy('id');
    }

    public function salePayment(): HasOne
    {
        return $this->hasOne(SalePayment::class);
    }
}
