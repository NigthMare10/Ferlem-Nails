<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const CARD_FEE_RATE = '4.00';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'total_services' => 'integer',
            'card_fee_rate' => 'decimal:2',
            'card_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Sale $sale) {
            if ($sale->isDirty('sale_number') && $sale->getOriginal('sale_number') !== null) {
                throw new LogicException('El número de venta es inmutable.');
            }
            if ($sale->getOriginal('sale_number') === null
                && array_diff(array_keys($sale->getDirty()), ['sale_number', 'updated_at']) === []) {
                return;
            }
            $allowed = ['status', 'canceled_at', 'canceled_by', 'cancellation_reason', 'updated_at'];
            if ($sale->getOriginal('status') === self::STATUS_COMPLETED
                && $sale->status === self::STATUS_CANCELED
                && array_diff(array_keys($sale->getDirty()), $allowed) === []) {
                return;
            }

            throw new LogicException('Las ventas confirmadas solo pueden anularse una vez.');
        });
        static::deleting(fn () => throw new LogicException('Las ventas no pueden eliminarse físicamente.'));
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('position')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('id');
    }
}
