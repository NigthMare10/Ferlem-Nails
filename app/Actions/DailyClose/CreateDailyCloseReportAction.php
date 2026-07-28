<?php

namespace App\Actions\DailyClose;

use App\Models\DailyCloseReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class CreateDailyCloseReportAction
{
    public function execute(CarbonImmutable $date, ?string $recipientEmail, string $trigger, ?User $requester = null): DailyCloseReport
    {
        $recipientEmail = $recipientEmail ? mb_strtolower(trim($recipientEmail)) : null;
        $base = implode('|', ['email', $trigger, $date->format('Y-m-d'), $recipientEmail]);
        $idempotencyKey = $trigger === DailyCloseReport::TRIGGER_SCHEDULED
            ? hash('sha256', $base)
            : hash('sha256', $base.'|'.Str::uuid());

        return DailyCloseReport::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'operational_date' => $date->format('Y-m-d'),
                'recipient_email' => $recipientEmail,
                'trigger' => $trigger,
                'status' => DailyCloseReport::STATUS_PENDING,
                'requested_by' => $requester?->getKey(),
            ],
        );
    }
}
