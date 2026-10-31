<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyGiftBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['service_snapshot' => 'array', 'expires_at' => 'immutable_datetime', 'points' => 'integer'];
    }

    public function recipients()
    {
        return $this->hasMany(LoyaltyGiftRecipient::class, 'batch_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
