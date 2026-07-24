<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAppointmentDepositResolution
{
    public function handle(Request $request, Closure $next): Response
    {
        $appointment = $request->route('appointment');
        $requiresResolution = $request->filled('deposit_resolution')
            || ($appointment instanceof Appointment
                && $appointment->deposit()->where('status', AppointmentDeposit::STATUS_PENDING)->exists());

        if ($requiresResolution) {
            $user = $request->user();
            abort_unless(
                $user?->is_active && $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT),
                403,
            );
        }

        return $next($request);
    }
}
