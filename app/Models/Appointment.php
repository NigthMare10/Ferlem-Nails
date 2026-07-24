<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Appointment extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_NO_SHOW = 'no_show';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'immutable_datetime',
            'scheduled_end' => 'immutable_datetime',
            'expected_total' => 'decimal:2',
            'expected_duration_minutes' => 'integer',
            'completed_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'no_show_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Las citas no pueden eliminarse físicamente.'));
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function noShowBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_show_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(AppointmentDeposit::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }
}
