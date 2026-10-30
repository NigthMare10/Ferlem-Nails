<?php

namespace App\Policies;

use App\Models\DailyCloseReport;
use App\Models\User;
use App\Support\Permissions;

class DailyCloseReportPolicy
{
    public function view(User $user, DailyCloseReport $report): bool
    {
        return $user->is_active && $user->can(Permissions::DAILY_CLOSE_VIEW);
    }

    public function retry(User $user, DailyCloseReport $report): bool
    {
        return $user->is_active
            && $user->can(Permissions::DAILY_CLOSE_SEND)
            && $report->status === DailyCloseReport::STATUS_FAILED;
    }
}
