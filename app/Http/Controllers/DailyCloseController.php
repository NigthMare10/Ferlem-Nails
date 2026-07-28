<?php

namespace App\Http\Controllers;

use App\Actions\DailyClose\CreateDailyCloseReportAction;
use App\Http\Requests\SendDailyCloseRequest;
use App\Http\Requests\UpdateDailyCloseSettingsRequest;
use App\Models\DailyCloseReport;
use App\Models\DailyCloseSetting;
use App\Services\DailyClosePdfGenerator;
use App\Services\DailyCloseReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DailyCloseController extends Controller
{
    private const AUDITED_FIELDS = [
        'enabled',
        'send_time',
        'timezone',
        'recipient_emails',
    ];

    public function index(): Response
    {
        $setting = $this->setting();
        $reports = DailyCloseReport::query()->with('requestedBy:id,name')->latest()->limit(20)->get();

        return Inertia::render('Configuration/DailyClose', [
            'setting' => [
                'enabled' => $setting->enabled,
                'send_time' => substr($setting->send_time, 0, 5),
                'timezone' => ReportPeriod::TIMEZONE,
                'recipient_emails' => $setting->recipient_emails ?? [],
            ],
            'lastReport' => ($lastReport = $reports->first(fn (DailyCloseReport $report) => $report->trigger !== DailyCloseReport::TRIGGER_DOWNLOAD))
                ? $this->reportResource($lastReport)
                : null,
            'reports' => $reports->map(fn (DailyCloseReport $report) => $this->reportResource($report))->values(),
        ]);
    }

    public function update(UpdateDailyCloseSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $setting = DailyCloseSetting::query()->lockForUpdate()->first() ?? new DailyCloseSetting;
            $previous = $setting->exists ? $setting->only(self::AUDITED_FIELDS) : null;
            $values = $request->validated();
            $values['timezone'] = ReportPeriod::TIMEZONE;
            $values['updated_by'] = $request->user()->getKey();
            $setting->fill($values)->save();

            $newValues = $setting->only(self::AUDITED_FIELDS);
            DB::table('daily_close_setting_events')->insert([
                'daily_close_setting_id' => $setting->getKey(),
                'performed_by' => $request->user()->getKey(),
                'occurred_at' => now('UTC'),
                'previous_values' => $previous ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
                'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        });

        return back()->with('success', 'Programación y destinatarios actualizados.');
    }

    public function send(
        SendDailyCloseRequest $request,
        CreateDailyCloseReportAction $create,
        DailyCloseReportService $service,
    ): RedirectResponse {
        return $this->sendReports($request, $create, $service, DailyCloseReport::TRIGGER_MANUAL);
    }

    public function test(
        SendDailyCloseRequest $request,
        CreateDailyCloseReportAction $create,
        DailyCloseReportService $service,
    ): RedirectResponse {
        return $this->sendReports($request, $create, $service, DailyCloseReport::TRIGGER_TEST);
    }

    public function generateDownload(
        Request $request,
        CreateDailyCloseReportAction $create,
        DailyClosePdfGenerator $pdf,
    ): StreamedResponse|RedirectResponse {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], ReportPeriod::TIMEZONE)->startOfDay()
            : CarbonImmutable::now(ReportPeriod::TIMEZONE)->startOfDay();
        $report = $create->execute($date, null, DailyCloseReport::TRIGGER_DOWNLOAD, $request->user());
        $report->forceFill([
            'status' => DailyCloseReport::STATUS_PROCESSING,
            'started_at' => now('UTC'),
            'attempts' => 1,
        ])->save();

        try {
            $report = $pdf->generate($report, $request->user(), $request->user()->name);
            $report->forceFill(['status' => DailyCloseReport::STATUS_SENT, 'sent_at' => now('UTC')])->save();

            return $this->downloadResponse($report);
        } catch (Throwable) {
            $report->forceFill([
                'status' => DailyCloseReport::STATUS_FAILED,
                'error_message' => 'No se pudo generar el PDF del cierre. Inténtalo nuevamente.',
                'failed_at' => now('UTC'),
            ])->save();

            return back()->with('error', 'No se pudo generar el PDF del cierre. No se guardó un informe parcial.');
        }
    }

    public function download(DailyCloseReport $dailyCloseReport): StreamedResponse
    {
        Gate::authorize('view', $dailyCloseReport);
        abort_unless($dailyCloseReport->pdf_path, 404);

        return $this->downloadResponse($dailyCloseReport);
    }

    public function retry(DailyCloseReport $dailyCloseReport, DailyCloseReportService $service): RedirectResponse
    {
        Gate::authorize('retry', $dailyCloseReport);

        try {
            $service->send($dailyCloseReport);

            return back()->with('success', 'Cierre diario enviado por correo.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function sendReports(
        SendDailyCloseRequest $request,
        CreateDailyCloseReportAction $create,
        DailyCloseReportService $service,
        string $trigger,
    ): RedirectResponse {
        $setting = $this->setting();
        if (empty($setting->recipient_emails)) {
            return back()->with('error', 'Agrega al menos un correo destinatario antes de enviar.');
        }

        $date = $request->validated('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->validated('date'), ReportPeriod::TIMEZONE)->startOfDay()
            : CarbonImmutable::now(ReportPeriod::TIMEZONE)->startOfDay();
        $reports = collect($setting->recipient_emails)
            ->map(fn (string $recipient) => $create->execute($date, $recipient, $trigger, $request->user()));

        try {
            $service->sendMany($reports);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $trigger === DailyCloseReport::TRIGGER_TEST
            ? 'Correo de prueba enviado correctamente.'
            : 'Cierre diario enviado por correo.');
    }

    private function setting(): DailyCloseSetting
    {
        return DailyCloseSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'send_time' => '21:00',
            'timezone' => ReportPeriod::TIMEZONE,
            'recipient_emails' => [],
        ]);
    }

    private function reportResource(DailyCloseReport $report): array
    {
        return [
            'id' => $report->getKey(),
            'date' => $report->operational_date->format('Y-m-d'),
            'date_label' => $report->operational_date->format('d/m/Y'),
            'recipient' => $report->recipient_email,
            'trigger' => $report->trigger,
            'status' => $report->status,
            'attempts' => $report->attempts,
            'error_message' => $report->error_message,
            'requested_by' => $report->requestedBy?->name,
            'created_at' => $report->created_at?->setTimezone(ReportPeriod::TIMEZONE)->format('d/m/Y h:i a'),
            'sent_at' => $report->sent_at?->setTimezone(ReportPeriod::TIMEZONE)->format('d/m/Y h:i a'),
            'download_url' => $report->pdf_path ? route('daily-close.reports.download', $report) : null,
            'retry_url' => $report->status === DailyCloseReport::STATUS_FAILED ? route('daily-close.reports.retry', $report) : null,
        ];
    }

    private function downloadResponse(DailyCloseReport $report): StreamedResponse
    {
        abort_unless($report->pdf_path && Storage::disk('daily_closures')->exists($report->pdf_path), 404);
        $stream = Storage::disk('daily_closures')->readStream($report->pdf_path);
        abort_unless(is_resource($stream), 404);
        $filename = 'Cierre-Studio-Lemus-'.$report->operational_date->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
