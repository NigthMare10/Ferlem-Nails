<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\User;

class UpdateAppointmentAction
{
    public function __construct(private readonly ApplyAppointmentChangesAction $changes) {}

    public function execute(User $user, Appointment $appointment, array $data): Appointment
    {
        return $this->changes->execute($user, $appointment, $data, false);
    }
}
