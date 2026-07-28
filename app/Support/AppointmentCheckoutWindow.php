<?php

namespace App\Support;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AppointmentCheckoutWindow
{
    public static function endsAt(Appointment $appointment, ?Collection $items = null): CarbonImmutable
    {
        $items ??= $appointment->relationLoaded('items') ? $appointment->items : $appointment->items()->get();

        return $items->max('scheduled_end')->setTimezone(CreateAppointmentAction::TIMEZONE);
    }

    public static function deadline(Appointment $appointment, ?Collection $items = null): CarbonImmutable
    {
        return self::endsAt($appointment, $items)->addMinutes((int) config('appointments.checkout_grace_minutes'));
    }

    public static function canCheckout(Appointment $appointment, CarbonImmutable $now, ?Collection $items = null): bool
    {
        return $now->lessThanOrEqualTo(self::deadline($appointment, $items));
    }
}
