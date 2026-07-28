<?php

namespace App\Mail;

use App\Models\DailyCloseReport;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DailyCloseReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DailyCloseReport $report,
    ) {}

    public function envelope(): Envelope
    {
        $date = CarbonImmutable::parse($this->report->operational_date->format('Y-m-d'), ReportPeriod::TIMEZONE);

        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            subject: 'Cierre diario Studio Lemus — '.$date->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-close',
            with: ['summary' => $this->report->summary_snapshot],
        );
    }

    public function attachments(): array
    {
        $filename = 'Cierre-Studio-Lemus-'.$this->report->operational_date->format('Y-m-d').'.pdf';

        return [
            Attachment::fromPath(Storage::disk('daily_closures')->path($this->report->pdf_path))
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
