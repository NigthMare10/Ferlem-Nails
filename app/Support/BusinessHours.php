<?php

namespace App\Support;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\BusinessHour;
use Carbon\CarbonImmutable;

final class BusinessHours
{
    public static function bounds(CarbonImmutable $day): ?array
    {
        $hours = BusinessHour::query()->where('weekday', $day->dayOfWeekIso)->first();
        if (! $hours?->is_open || ! $hours->opens_at || ! $hours->closes_at) {
            return null;
        }

        return [
            CarbonImmutable::createFromFormat('!Y-m-d H:i', $day->format('Y-m-d').' '.substr($hours->opens_at, 0, 5), CreateAppointmentAction::TIMEZONE),
            CarbonImmutable::createFromFormat('!Y-m-d H:i', $day->format('Y-m-d').' '.substr($hours->closes_at, 0, 5), CreateAppointmentAction::TIMEZONE),
        ];
    }

    public static function contains(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $bounds = self::bounds($start);

        return $bounds !== null
            && $start->isSameDay($end)
            && $start->greaterThanOrEqualTo($bounds[0])
            && $end->lessThanOrEqualTo($bounds[1]);
    }
}
