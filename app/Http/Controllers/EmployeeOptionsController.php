<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\EmployeeBreak;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeOptionsController extends Controller
{
    public function show(Request $request, User $user)
    {
        abort_unless($request->user('web')->hasRole('owner'), 403);

        return Inertia::render('Configuration/EmployeeOptions', [
            'employee' => [...$user->only('id', 'name', 'display_order'), 'is_owner' => $user->hasRole('owner')],
            'catalog' => Permissions::all(),
            'direct' => $user->permissions->pluck('name'),
            'inherited' => $user->getPermissionsViaRoles()->pluck('name')->unique()->values(),
            'breaks' => EmployeeBreak::where('user_id', $user->id)->get(),
        ]);
    }

    public function permissions(Request $request, User $user)
    {
        abort_unless($request->user('web')->hasRole('owner'), 403);
        abort_if($user->is($request->user('web')) || $user->hasRole('owner'), 422, 'Los permisos del propietario se administran mediante su rol.');
        $data = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(Permissions::all())]]);
        DB::transaction(function () use ($user, $request, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = $user->permissions->pluck('name')->all();
            $user->syncPermissions($data['permissions']);
            $this->audit($request, $user, 'permissions.updated', $before, $data['permissions']);
        });

        return back()->with('success', 'Permisos individuales actualizados.');
    }

    public function breaks(Request $request, User $user)
    {
        abort_unless($request->user('web')->hasRole('owner'), 403);
        $data = $request->validate([
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'breaks' => ['present', 'array', 'max:40'],
            'breaks.*.weekday' => ['nullable', 'integer', 'between:0,6'],
            'breaks.*.date' => ['nullable', 'date_format:Y-m-d'],
            'breaks.*.starts_at' => ['required', 'date_format:H:i'],
            'breaks.*.ends_at' => ['required', 'date_format:H:i'],
        ]);
        DB::transaction(function () use ($request, $user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $items = AppointmentItem::where('assigned_to', $user->id)
                ->where('scheduled_end', '>', now('UTC'))
                ->whereHas('appointment', fn ($q) => $q->where('status', Appointment::STATUS_SCHEDULED))->get();
            foreach ($data['breaks'] as $index => $break) {
                if (($break['weekday'] ?? null) === null && empty($break['date']) || $break['starts_at'] >= $break['ends_at']) {
                    throw ValidationException::withMessages(["breaks.$index.starts_at" => 'Selecciona día o fecha y un intervalo válido.']);
                }
                foreach (array_slice($data['breaks'], 0, $index) as $previous) {
                    $sameScope = ($break['date'] ?? null) === ($previous['date'] ?? null)
                        && (! empty($break['date']) || (int) ($break['weekday'] ?? -1) === (int) ($previous['weekday'] ?? -1));
                    if ($sameScope && $break['starts_at'] < $previous['ends_at'] && $break['ends_at'] > $previous['starts_at']) {
                        throw ValidationException::withMessages(['breaks' => 'Hay comidas superpuestas para el mismo día.']);
                    }
                }
                foreach ($items as $item) {
                    $start = CarbonImmutable::instance($item->scheduled_start)->setTimezone(CreateAppointmentAction::TIMEZONE);
                    $end = CarbonImmutable::instance($item->scheduled_end)->setTimezone(CreateAppointmentAction::TIMEZONE);
                    if (empty($break['date']) && collect($data['breaks'])->contains(fn ($candidate) => ($candidate['date'] ?? null) === $start->format('Y-m-d'))) {
                        continue;
                    }
                    $matches = ! empty($break['date']) ? $start->format('Y-m-d') === $break['date'] : $start->dayOfWeek === (int) $break['weekday'];
                    if ($matches && $start->format('H:i') < $break['ends_at'] && $end->format('H:i') > $break['starts_at']) {
                        throw ValidationException::withMessages(['breaks' => 'La comida se superpone con una cita existente del '.$start->format('d/m/Y').'. Reprograma primero la cita.']);
                    }
                }
            }
            $before = EmployeeBreak::where('user_id', $user->id)->get()->toArray();
            EmployeeBreak::where('user_id', $user->id)->delete();
            foreach ($data['breaks'] as $break) {
                EmployeeBreak::create(['user_id' => $user->id, 'weekday' => empty($break['date']) ? $break['weekday'] : null, 'date' => $break['date'] ?? null, 'starts_at' => $break['starts_at'].':00', 'ends_at' => $break['ends_at'].':00']);
            }
            $user->forceFill(['display_order' => $data['display_order']])->save();
            $this->audit($request, $user, 'availability.updated', $before, $data);
        });

        return back()->with('success', 'Orden y comidas actualizados.');
    }

    private function audit(Request $request, User $user, string $action, array $before, array $after): void
    {
        DB::table('security_audits')->insert(['actor_id' => $request->user('web')->id, 'subject_type' => 'user', 'subject_id' => $user->id, 'action' => $action, 'before' => json_encode($before), 'after' => json_encode($after), 'created_at' => now(), 'updated_at' => now()]);
    }
}
