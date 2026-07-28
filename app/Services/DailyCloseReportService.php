<?php

namespace App\Services;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\DailyCloseReport;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DailyCloseReportService
{
    public function __construct(
        private readonly DailyClosePdfGenerator $pdfGenerator,
        private readonly DailyCloseEmailSender $emailSender,
        private readonly PublishInternalNotificationAction $notifications,
    ) {}

    public function send(DailyCloseReport $report): DailyCloseReport
    {
        return $this->sendMany([$report])->firstOrFail();
    }

    /** @return Collection<int, DailyCloseReport> */
    public function sendMany(iterable $reports): Collection
    {
        $reports = collect($reports)
            ->map(fn (DailyCloseReport $report) => $report->fresh())
            ->filter(fn (DailyCloseReport $report) => $report->status !== DailyCloseReport::STATUS_SENT)
            ->unique('id')
            ->values();

        if ($reports->isEmpty()) {
            return collect();
        }

        $reports = DB::transaction(fn () => $reports->map(function (DailyCloseReport $report) {
            $locked = DailyCloseReport::query()->lockForUpdate()->findOrFail($report->getKey());
            $locked->forceFill([
                'status' => DailyCloseReport::STATUS_PROCESSING,
                'attempts' => $locked->attempts + 1,
                'started_at' => now('UTC'),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            return $locked;
        }));

        $actor = $reports->first()->requestedBy()->first()
            ?? User::query()->where('is_active', true)->role('owner')->orderBy('id')->first();
        if (! $actor) {
            $this->failReports($reports, 'No existe un propietario activo para generar el cierre.');
            throw new RuntimeException('No existe un propietario activo para generar el cierre.');
        }

        try {
            $source = $reports->first(fn (DailyCloseReport $report) => $report->pdf_path
                && $report->summary_snapshot
                && Storage::disk('daily_closures')->exists($report->pdf_path));

            if (! $source) {
                $source = $this->pdfGenerator->generate(
                    $reports->first(),
                    $actor,
                    $reports->first()->requested_by ? $actor->name : 'Proceso automático',
                );
            }

            $reports->each(function (DailyCloseReport $report) use ($source) {
                if ($report->is($source)) {
                    return;
                }

                $report->forceFill([
                    'pdf_path' => $source->pdf_path,
                    'pdf_sha256' => $source->pdf_sha256,
                    'pdf_mime' => $source->pdf_mime,
                    'summary_snapshot' => $source->summary_snapshot,
                ])->save();
            });
        } catch (Throwable) {
            $message = 'No se pudo generar el PDF del cierre. No se envió ningún correo.';
            $this->failReports($reports, $message, $actor);
            throw new RuntimeException($message);
        }

        $failed = false;
        $reports->each(function (DailyCloseReport $report) use ($actor, &$failed) {
            try {
                $messageId = $this->emailSender->send($report->fresh());
                $report->forceFill([
                    'status' => DailyCloseReport::STATUS_SENT,
                    'external_message_id' => $messageId,
                    'sent_at' => now('UTC'),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
                $this->publish($actor, $report, true);
            } catch (Throwable) {
                $failed = true;
                $message = 'No se pudo entregar el correo mediante el servidor SMTP configurado.';
                $report->forceFill([
                    'status' => DailyCloseReport::STATUS_FAILED,
                    'error_message' => $message,
                    'failed_at' => now('UTC'),
                ])->save();
                $this->publish($actor, $report, false);
            }
        });

        if ($failed) {
            throw new RuntimeException('El PDF se generó, pero uno o más correos no pudieron entregarse.');
        }

        return $reports->map->fresh();
    }

    private function failReports(Collection $reports, string $message, ?User $actor = null): void
    {
        $reports->each(function (DailyCloseReport $report) use ($actor, $message) {
            $report->forceFill([
                'status' => DailyCloseReport::STATUS_FAILED,
                'error_message' => $message,
                'failed_at' => now('UTC'),
            ])->save();
            if ($actor) {
                $this->publish($actor, $report, false);
            }
        });
    }

    private function publish(User $actor, DailyCloseReport $report, bool $sent): void
    {
        try {
            $message = $sent
                ? 'El informe del '.$report->operational_date->format('d/m/Y').' fue enviado por correo electrónico.'
                : 'No fue posible enviar el informe del '.$report->operational_date->format('d/m/Y').'. Revisa la configuración e inténtalo nuevamente.';
            $this->notifications->execute(
                $actor,
                $sent ? 'daily_close.sent' : 'daily_close.failed',
                $sent ? 'Cierre diario enviado' : 'Error en el cierre diario',
                $message,
                '/configuration/daily-close',
                ['type' => 'daily_close_report', 'id' => $report->getKey()],
                'daily-close-report:'.$report->getKey().':'.($sent ? 'sent' : 'failed').':'.$report->attempts,
                now('UTC'),
                Permissions::DAILY_CLOSE_VIEW,
            );
            if ($report->requested_by) {
                $this->notifications->executeForRecipients(
                    $actor,
                    $sent ? 'daily_close.sent' : 'daily_close.failed',
                    $sent ? 'Cierre diario enviado' : 'Error en el cierre diario',
                    $message,
                    '/configuration/daily-close',
                    ['type' => 'daily_close_report', 'id' => $report->getKey()],
                    'daily-close-report:'.$report->getKey().':'.($sent ? 'sent' : 'failed').':'.$report->attempts,
                    now('UTC'),
                    [$report->requested_by],
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
