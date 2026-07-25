<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function internalNotifications(): MorphMany
    {
        return $this->morphMany(InternalNotification::class, 'notifiable')->latest();
    }

    public function openedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'opened_by');
    }

    public function closedCashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sold_by');
    }

    public function canceledSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'canceled_by');
    }

    public function performedSaleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'performed_by');
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'assigned_to');
    }

    public function assignedAppointmentItems(): HasMany
    {
        return $this->hasMany(AppointmentItem::class, 'assigned_to');
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function performedAppointmentEvents(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class, 'performed_by');
    }

    public function recordedAppointmentDeposits(): HasMany
    {
        return $this->hasMany(AppointmentDeposit::class, 'recorded_by');
    }

    public function resolvedAppointmentDeposits(): HasMany
    {
        return $this->hasMany(AppointmentDeposit::class, 'resolved_by');
    }

    public function refundedAppointmentDeposits(): HasMany
    {
        return $this->hasMany(AppointmentDepositRefund::class, 'refunded_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
