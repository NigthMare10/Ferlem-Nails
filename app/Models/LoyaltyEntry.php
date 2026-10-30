<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'qualifying_points' => 'integer', 'customer_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los movimientos de puntos son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Los movimientos de puntos no pueden eliminarse.'));
    }
}
