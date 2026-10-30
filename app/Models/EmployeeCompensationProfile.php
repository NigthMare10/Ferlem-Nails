<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCompensationProfile extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::creating(function (EmployeeCompensationProfile $profile): void {
            $profile->contract_start_date ??= $profile->effective_from;
            $profile->is_indefinite ??= $profile->contract_end_date === null;
            $profile->auto_generate_payroll_expense ??= false;
        });
    }

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'contract_start_date' => 'immutable_date',
            'contract_end_date' => 'immutable_date',
            'is_indefinite' => 'boolean',
            'auto_generate_payroll_expense' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(PayrollObligation::class, 'compensation_profile_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayrollEvent::class, 'subject_id')
            ->where('subject_type', self::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
