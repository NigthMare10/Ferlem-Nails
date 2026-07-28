<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Support\LandingDestination;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, LandingDestination $landing): Response|RedirectResponse
    {
        if (! $landing->canAccessHome($request->user())) {
            $destination = $landing->for($request->user());
            abort_if($destination === null, 403);

            return redirect()->to($destination);
        }

        $metrics = [];

        if ($request->user()->can(Permissions::SERVICES_VIEW)) {
            $metrics['active_services'] = Service::query()->where('is_active', true)->count();
        }

        if ($request->user()->can(Permissions::USERS_VIEW)) {
            $metrics['active_users'] = User::query()->where('is_active', true)->count();
        }

        $today = CarbonImmutable::now(CreateAppointmentAction::TIMEZONE);
        $todayStart = $today->startOfDay();
        $expiredBefore = $today->subMinutes((int) config('appointments.checkout_grace_minutes'))->utc();
        $todayAppointmentsQuery = Appointment::query()
            ->with(['assignedTo:id,name', 'items.assignedTo:id,name', 'deposit', 'sale:id,appointment_id'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_start', '>=', $todayStart->utc())
            ->where('scheduled_start', '<', $todayStart->addDay()->utc())
            ->whereHas('items', fn ($items) => $items->where('scheduled_end', '>', $expiredBefore))
            ->when(
                ! $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL),
                fn ($query) => $query->whereHas('items', fn ($items) => $items->where('assigned_to', $request->user()->getKey())),
            )
            ->orderBy('scheduled_start')->orderBy('id');
        $allTodayAppointments = $todayAppointmentsQuery->get();
        $viewAll = $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL);
        $appointments = $allTodayAppointments->take(4);
        $scheduledServices = $allTodayAppointments->sum(fn (Appointment $appointment) => $appointment->items
            ->when(! $viewAll, fn ($items) => $items->where('assigned_to', $request->user()->getKey()))->count());

        return Inertia::render('Home', [
            'metrics' => $metrics,
            'today' => $today->format('Y-m-d'),
            'todayAgenda' => ['appointments_count' => $allTodayAppointments->count(), 'services_count' => $scheduledServices],
            'todayAppointments' => AppointmentResource::collection($appointments)->resolve($request),
        ]);
    }
}
