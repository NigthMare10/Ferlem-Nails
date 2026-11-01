<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyRedemption extends Model
{
    protected $guarded = ['id'];

    public function appointment()
    {
        return $this->hasOne(Appointment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservedBy()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'reward_snapshot' => 'array'];
    }
}
