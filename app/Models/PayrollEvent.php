<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollEvent extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de nómina no pueden modificarse.'));
        static::deleting(fn () => throw new LogicException('Los eventos de nómina no pueden eliminarse.'));
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
