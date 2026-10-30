<?php

namespace App\Support;

use App\Models\Appointment;

final class AppointmentRevision
{
    public static function of(Appointment $appointment): string
    {
        return hash('sha256', json_encode(['appointment' => $appointment->fresh()->getAttributes(), 'items' => $appointment->items()->orderBy('id')->get()->map->getAttributes()->all()], JSON_THROW_ON_ERROR));
    }
}
