<?php

namespace App\Services;

use App\Mail\DailyCloseReportMail;
use App\Models\DailyCloseReport;
use Illuminate\Mail\MailManager;
use RuntimeException;
use Throwable;

class DailyCloseEmailSender
{
    public function __construct(private readonly MailManager $mailManager) {}

    public function send(DailyCloseReport $report): ?string
    {
        $mailer = (string) config('mail.default');
        $mailerConfig = config("mail.mailers.{$mailer}");
        if (
            ! is_array($mailerConfig)
            || ($mailerConfig['transport'] ?? null) !== 'smtp'
            || ! filled($mailerConfig['host'] ?? null)
            || ! filled($mailerConfig['port'] ?? null)
            || ! filled(config('mail.from.address'))
        ) {
            throw new RuntimeException('El servidor de correo no está configurado en el entorno de la aplicación.');
        }
        if (! $report->recipient_email || ! $report->pdf_path || ! $report->summary_snapshot) {
            throw new RuntimeException('El informe de cierre está incompleto.');
        }

        try {
            $sent = $this->mailManager->mailer($mailer)
                ->to($report->recipient_email)
                ->send(new DailyCloseReportMail($report));

            return $sent?->getMessageId();
        } catch (Throwable) {
            throw new RuntimeException('No se pudo entregar el correo mediante el servidor SMTP configurado.');
        }
    }
}
