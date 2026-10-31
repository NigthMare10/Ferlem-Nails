<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyReward extends Model
{
    protected $guarded = ['id'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'birthday_only' => 'boolean'];
    }
}
