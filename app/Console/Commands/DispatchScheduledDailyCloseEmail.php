<?php

namespace App\Console\Commands;

use App\Actions\DailyClose\CreateDailyCloseReportAction;
use App\Models\DailyCloseReport;
use App\Models\DailyCloseSetting;
use App\Services\DailyCloseReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

class DispatchScheduledDailyCloseEmail extends Command
{
    protected $signature = 'studio:dispatch-daily-close-email';

    protected $description = 'Envía por correo el cierre diario cuando alcanza la hora de Honduras';

    public function handle(CreateDailyCloseReportAction $create, DailyCloseReportService $service): int
    {
        $setting = DailyCloseSetting::query()->where('enabled', true)->first();
        if (! $setting || empty($setting->recipient_emails)) {
            return self::SUCCESS;
        }

        $now = CarbonImmutable::now('America/Tegucigalpa');
        if ($now->format('H:i') < substr($setting->send_time, 0, 5)) {
            return self::SUCCESS;
        }

        $reports = collect($setting->recipient_emails)
            ->map(fn (string $recipient) => $create->execute($now->startOfDay(), $recipient, DailyCloseReport::TRIGGER_SCHEDULED))
            ->filter(fn (DailyCloseReport $report) => $report->wasRecentlyCreated || $report->status === DailyCloseReport::STATUS_PENDING)
            ->values();

        if ($reports->isEmpty()) {
            return self::SUCCESS;
        }

        try {
            $service->sendMany($reports);
            $this->info('Cierre diario enviado a '.$reports->count().' destinatario(s).');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
