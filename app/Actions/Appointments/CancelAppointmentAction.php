<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Permissions;

class CancelAppointmentAction
{
    public function __construct(private TransitionAppointmentStatusAction $transition) {}

    public function execute(User $user, Appointment $appointment, string $reason): Appointment
    {
        return $this->transition->execute(
            $user,
            $appointment,
            $reason,
            Appointment::STATUS_CANCELED,
            Permissions::APPOINTMENTS_CANCEL,
        );
    }
}
