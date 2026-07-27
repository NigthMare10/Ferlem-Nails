<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollObligation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELED = 'canceled';

    public const INSTALLMENT_FIRST = 'first';

    public const INSTALLMENT_SECOND = 'second';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'immutable_date', 'amount' => 'decimal:2',
            'generated_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime', 'processing_failed_at' => 'immutable_datetime',
            'processing_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmployeeCompensationProfile::class, 'compensation_profile_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayrollEvent::class, 'subject_id')
            ->where('subject_type', self::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
