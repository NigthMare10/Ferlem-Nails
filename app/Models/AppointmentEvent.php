<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AppointmentEvent extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_RESCHEDULED = 'rescheduled';

    public const TYPE_CANCELED = 'canceled';

    public const TYPE_NO_SHOW = 'no_show';

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
        static::updating(fn () => throw new LogicException('Los eventos de cita son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de cita son inmutables.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
