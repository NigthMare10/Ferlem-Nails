<?php

namespace App\Services;

use App\Models\DailyCloseReport;
use App\Models\User;
use App\Support\DailyCloseReportData;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DailyClosePdfGenerator
{
    public function __construct(private readonly DailyCloseReportData $reportData) {}

    public function generate(DailyCloseReport $report, User $viewer, string $generatedBy): DailyCloseReport
    {
        $date = CarbonImmutable::parse($report->operational_date->format('Y-m-d'), ReportPeriod::TIMEZONE);
        $data = $this->reportData->build($date, $viewer, $generatedBy);
        $logoPath = resource_path('images/studio-lemus-logo-pdf.png');
        $normalizedLogoPath = str_replace('\\', '/', $logoPath);
        $logo = is_file($logoPath)
            ? 'file://'.(str_starts_with($normalizedLogoPath, '/') ? '' : '/').$normalizedLogoPath
            : null;
        $path = sprintf('%s/%s/%s.pdf', $date->format('Y'), $date->format('m'), Str::uuid());
        $pdf = null;
        $dompdf = null;

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('dpi', 96);
            $options->set('chroot', [resource_path(), storage_path()]);

            $html = view('pdf.daily-close', [...$data, 'logo' => $logo])->render();
            $dompdf = new Dompdf($options);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            unset($html);
            $dompdf->render();
            $dompdf->getCanvas()->page_text(
                42,
                754,
                'Generado '.$data['generated_at'].'  |  Página {PAGE_NUM} de {PAGE_COUNT}  |  Documento confidencial',
                null,
                7,
                [0.34, 0.27, 0.31],
            );
            $pdf = $dompdf->output();
            if (! str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('No se pudo generar un documento PDF válido.');
            }

            Storage::disk('daily_closures')->put($path, $pdf);
            $report->forceFill([
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $pdf),
                'pdf_mime' => 'application/pdf',
                'summary_snapshot' => $data['actual'],
            ])->save();

            return $report->refresh();
        } catch (Throwable $exception) {
            if (Storage::disk('daily_closures')->exists($path)) {
                Storage::disk('daily_closures')->delete($path);
            }

            throw $exception;
        } finally {
            unset($pdf, $dompdf, $data);
            gc_collect_cycles();
        }
    }
}
