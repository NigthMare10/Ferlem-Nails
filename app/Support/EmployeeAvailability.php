<?php

namespace App\Support;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\EmployeeBreak;
use Carbon\CarbonImmutable;

final class EmployeeAvailability
{
    public static function hasBreak(int $userId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $start = $start->setTimezone(CreateAppointmentAction::TIMEZONE);
        $end = $end->setTimezone(CreateAppointmentAction::TIMEZONE);
        $date = $start->format('Y-m-d');
        $exceptions = EmployeeBreak::where('user_id', $userId)->where('date', $date)->get();
        $breaks = $exceptions->isNotEmpty() ? $exceptions : EmployeeBreak::where('user_id', $userId)->whereNull('date')->where('weekday', $start->dayOfWeek)->get();

        return $breaks->contains(fn ($break) => $start->format('H:i:s') < $break->ends_at && $end->format('H:i:s') > $break->starts_at);
    }
}
