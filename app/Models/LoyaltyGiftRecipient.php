<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyGiftRecipient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['applied_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function batch()
    {
        return $this->belongsTo(LoyaltyGiftBatch::class, 'batch_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
