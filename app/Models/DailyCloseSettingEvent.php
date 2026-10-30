<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DailyCloseSettingEvent extends Model
{
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
        static::updating(fn () => throw new LogicException('La auditoría de configuración es inmutable.'));
        static::deleting(fn () => throw new LogicException('La auditoría de configuración no puede eliminarse.'));
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(DailyCloseSetting::class, 'daily_close_setting_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
