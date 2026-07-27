<?php

namespace App\Console\Commands;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\PayrollObligation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Console\Command;

class NotifyPayrollDue extends Command
{
    protected $signature = 'studio:notify-payroll-due';

    protected $description = 'Notifica obligaciones salariales próximas o vencidas sin repetir avisos.';

    public function handle(PublishInternalNotificationAction $notifications): int
    {
        $actor = User::query()->where('is_active', true)->first();
        if (! $actor) {
            return self::SUCCESS;
        }
        $today = now('America/Tegucigalpa')->toDateString();
        PayrollObligation::query()->where('status', PayrollObligation::STATUS_PENDING)->where(function ($query) use ($today): void {
            $query->where('scheduled_date', '<', $today)->orWhere('scheduled_date', now('America/Tegucigalpa')->addDays(3)->toDateString());
        })->eachById(function (PayrollObligation $obligation) use ($notifications, $actor, $today) {
            $overdue = $obligation->scheduled_date->toDateString() < $today;
            $type = $overdue ? 'payroll.overdue' : 'payroll.due_soon';
            $notifications->execute($actor, $type, $overdue ? 'Salario vencido' : 'Salario próximo a vencer', $overdue ? 'Hay una obligación de nómina vencida.' : 'Una obligación de nómina vence en tres días.', '/expenses?section=payroll', ['type' => 'payroll_obligation', 'id' => $obligation->id], "{$type}:{$obligation->id}", now('UTC'), Permissions::PAYROLL_VIEW);
        });

        return self::SUCCESS;
    }
}
