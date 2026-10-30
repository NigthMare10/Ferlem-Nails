<?php

namespace App\Console\Commands;

use App\Actions\DailyClose\CreateDailyCloseReportAction;
use App\Models\DailyCloseReport;
use App\Models\DailyCloseSetting;
use App\Services\DailyCloseReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SendDailyCloseEmail extends Command
{
    protected $signature = 'studio:send-daily-close-email {--date=} {--force}';

    protected $description = 'Genera y envía por correo un informe de cierre diario';

    public function handle(CreateDailyCloseReportAction $create, DailyCloseReportService $service): int
    {
        $setting = DailyCloseSetting::query()->first();
        if (! $setting || empty($setting->recipient_emails)) {
            $this->error('Agrega al menos un destinatario en Configuración → Cierre diario.');

            return self::FAILURE;
        }

        try {
            $dateInput = $this->option('date') ? (string) $this->option('date') : null;
            $date = $dateInput
                ? CarbonImmutable::createFromFormat('!Y-m-d', $dateInput, ReportPeriod::TIMEZONE)->startOfDay()
                : CarbonImmutable::now(ReportPeriod::TIMEZONE)->startOfDay();
            if ($dateInput && $date->format('Y-m-d') !== $dateInput) {
                throw new \InvalidArgumentException;
            }
        } catch (Throwable) {
            $this->error('La fecha debe usar el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        $trigger = $this->option('force') ? DailyCloseReport::TRIGGER_MANUAL : DailyCloseReport::TRIGGER_SCHEDULED;
        $reports = collect($setting->recipient_emails)
            ->map(fn (string $recipient) => $create->execute($date, $recipient, $trigger))
            ->filter(fn (DailyCloseReport $report) => $this->option('force') || $report->wasRecentlyCreated || $report->status === DailyCloseReport::STATUS_PENDING)
            ->values();

        if ($reports->isEmpty()) {
            $this->warn('El cierre ya fue procesado para esta fecha y destinatarios. Usa --force para enviarlo nuevamente.');

            return self::SUCCESS;
        }

        try {
            $service->sendMany($reports);
            $this->info('Cierre diario enviado a '.$reports->count().' destinatario(s).');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
