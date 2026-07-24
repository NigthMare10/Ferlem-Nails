<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SaleItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'position' => 'integer',
            'allocated_card_fee_amount' => 'decimal:2',
            'net_line_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleItem $item) {
            $sale = $item->sale()->firstOrFail();
            $item->performed_by ??= $sale->sold_by;
            $item->position ??= ((int) $sale->items()->max('position')) + 1;
            $item->allocated_card_fee_amount ??= '0.00';
            $item->net_line_amount ??= $item->line_total;
        });
        static::updating(fn () => throw new LogicException('Las líneas de una venta son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las líneas de una venta no pueden eliminarse.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function appointmentItem(): BelongsTo
    {
        return $this->belongsTo(AppointmentItem::class);
    }
}
