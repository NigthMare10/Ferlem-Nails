<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\User;

class UpdateAppointmentAction
{
    public function __construct(private readonly ApplyAppointmentChangesAction $changes) {}

    public function execute(User $user, Appointment $appointment, array $data): Appointment
    {
        if (isset($data['items'])) {
            return app(EditAppointmentServicesAction::class)->execute($user, $appointment, $data);
        }

        return $this->changes->execute($user, $appointment, $data, false);
    }
}
