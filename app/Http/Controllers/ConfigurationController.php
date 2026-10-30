<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;

class ConfigurationController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user->can(Permissions::SETTINGS_ACCESS), 403);
        if ($user->can(Permissions::USERS_VIEW)) {
            return redirect()->route('configuration.users.index');
        }
        if ($user->can(Permissions::SERVICES_VIEW)) {
            return redirect()->route('configuration.services.index');
        }
        if ($user->can(Permissions::SETTINGS_BUSINESS_HOURS_MANAGE)) {
            return redirect()->route('configuration.business-hours.index');
        }
        if ($user->can(Permissions::DAILY_CLOSE_VIEW)) {
            return redirect()->route('configuration.daily-close.index');
        }
        abort(403);
    }
}
