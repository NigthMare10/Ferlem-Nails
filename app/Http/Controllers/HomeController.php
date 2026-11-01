<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\LoyaltyRedemption;
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
        $todayAppointmentsQuery = Appointment::query()
            ->with(['assignedTo:id,name', 'items.assignedTo:id,name', 'deposit', 'sale:id,appointment_id'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_start', '>=', $todayStart->utc())
            ->where('scheduled_start', '<', $todayStart->addDay()->utc())
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
            'pendingRewardRedemptions' => function () use ($request) {
                if (! $request->user()->can('loyalty.manage')) {
                    return [];
                }
                $redemptions = LoyaltyRedemption::with('customer:id,name,phone')
                    ->where('status', 'reserved')->where('expires_at', '>', now())->where('reward_snapshot->benefit_type', 'service')->orderBy('created_at')->limit(20)->get();
                $services = Service::whereIn('id', $redemptions->map(fn ($r) => $r->reward_snapshot['service_id'] ?? null)->filter())->get(['id', 'is_active'])->keyBy('id');

                return $redemptions->map(function ($r) use ($services) {
                    $s = $services->get($r->reward_snapshot['service_id'] ?? null);

                    return ['id' => $r->id, 'client_name' => $r->customer->name, 'client_phone' => $r->customer->phone, 'service_id' => $s?->id, 'service_name' => $r->reward_snapshot['name'], 'expires_at' => $r->expires_at->toIso8601String(), 'service_available' => (bool) $s?->is_active];
                });
            },
            'rewardServices' => fn () => $request->user()->can('loyalty.manage') ? Service::where('is_active', true)->orderBy('name')->get(['id', 'name', 'description', 'price', 'duration_minutes']) : [],
            'rewardAssignees' => fn () => $request->user()->can('loyalty.manage') ? User::where('is_active', true)->permission(Permissions::APPOINTMENTS_PERFORM)->when(! $request->user()->can(Permissions::APPOINTMENTS_ASSIGN), fn ($q) => $q->whereKey($request->user()->id))->orderBy('display_order')->orderBy('name')->get(['id', 'name']) : [],
            'metrics' => $metrics,
            'today' => $today->format('Y-m-d'),
            'todayAgenda' => ['appointments_count' => $allTodayAppointments->count(), 'services_count' => $scheduledServices],
            'todayAppointments' => AppointmentResource::collection($appointments)->resolve($request),
        ]);
    }
}
