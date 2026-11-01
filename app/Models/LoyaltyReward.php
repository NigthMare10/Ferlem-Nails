<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyReward extends Model
{
    protected $hidden = ['image_path'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? url('/rewards/'.$this->id.'/image').'?v='.($this->version ?? 1) : null;
    }

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
