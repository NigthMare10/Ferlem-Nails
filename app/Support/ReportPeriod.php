<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

final class ReportPeriod
{
    public const TIMEZONE = 'America/Tegucigalpa';

    public static function bounds(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $reference = isset($filters['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date'], self::TIMEZONE)->startOfDay()
            : $today;

        return match ($period) {
            'today' => [$reference, $reference->addDay(), $reference],
            'week' => [
                $reference->startOfWeek(CarbonInterface::MONDAY),
                $reference->startOfWeek(CarbonInterface::MONDAY)->addWeek(),
                $reference,
            ],
            'month' => [$reference->startOfMonth(), $reference->startOfMonth()->addMonth(), $reference],
            'custom' => [
                CarbonImmutable::createFromFormat('Y-m-d', $filters['date_from'], self::TIMEZONE)->startOfDay(),
                CarbonImmutable::createFromFormat('Y-m-d', $filters['date_to'], self::TIMEZONE)->startOfDay()->addDay(),
                $today,
            ],
            default => throw new RuntimeException('Periodo de reporte no soportado.'),
        };
    }

    public static function label(string $period, CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($period) {
            'today' => $start->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'month' => ucfirst($start->locale('es')->translatedFormat('F \\d\\e Y')),
            default => $start->format('d/m/Y').' al '.$end->subDay()->format('d/m/Y'),
        };
    }
}
