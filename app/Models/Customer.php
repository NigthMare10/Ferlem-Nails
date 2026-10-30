<?php

namespace App\Models;

use App\Notifications\CustomerResetPassword;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'email', 'password', 'phone', 'birthday'];

    protected $hidden = ['password', 'remember_token', 'avatar_path'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function entries()
    {
        return $this->hasMany(LoyaltyEntry::class);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomerVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomerResetPassword($token));
    }
}
