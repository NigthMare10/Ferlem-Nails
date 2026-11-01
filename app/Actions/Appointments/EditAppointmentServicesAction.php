<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentRevision;
use App\Support\BusinessHours;
use App\Support\EmployeeAvailability;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditAppointmentServicesAction
{
    public function execute(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless($actor->is_active && $actor->can('appointments.edit_services'), 403);

        return DB::transaction(function () use ($actor, $appointment, $data) {
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($locked->loyalty_redemption_id) {
                throw ValidationException::withMessages(['appointment' => 'No se puede cambiar el servicio de un premio. Usa Reprogramar para ajustar fecha, hora o persona.']);
            }
            $items = $locked->items()->orderBy('position')->lockForUpdate()->get()->keyBy('id');
            if (! hash_equals(AppointmentRevision::of($locked), $data['expected_revision'])) {
                throw ValidationException::withMessages(['appointment' => 'Otra persona cambió esta cita. Recarga antes de continuar.']);
            }
            abort_unless($actor->can('appointments.update_any') || $actor->hasPermissionTo('appointments.view_all') || $items->every(fn ($i) => $i->assigned_to === $actor->id), 403);
            if ($locked->status !== 'scheduled' || $locked->sale()->exists()) {
                throw ValidationException::withMessages(['appointment' => 'La cita ya no puede editarse.']);
            }
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start_time'], CreateAppointmentAction::TIMEZONE);
            if ($start->isPast() || $start->minute % (int) config('appointments.slot_minutes') !== 0) {
                throw ValidationException::withMessages(['start_time' => 'Selecciona un horario futuro válido.']);
            }
            $ids = collect($data['items'])->pluck('assigned_to')->merge($items->pluck('assigned_to'))->unique()->sort();
            $users = User::whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $services = Service::whereKey(collect($data['items'])->pluck('service_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $before = ['items' => $items->toArray(), 'total' => $locked->expected_total];
            $seen = [];
            $prepared = [];
            $cursor = $start;
            $total = 0;
            foreach ($data['items'] as $position => $input) {
                $old = empty($input['id']) ? null : $items->get((int) $input['id']);
                if (! empty($input['id']) && (! $old || in_array($old->id, $seen, true))) {
                    throw ValidationException::withMessages(['items' => 'Un servicio no pertenece a esta cita o está repetido.']);
                }
                if ($old) {
                    $seen[] = $old->id;
                }
                $assigned = (int) $input['assigned_to'];
                if (! $actor->can('appointments.assign') && $assigned !== ($old?->assigned_to ?? $actor->id)) {
                    abort(403);
                }
                $person = $users->get($assigned);
                if (! $person?->is_active || ! $person->hasPermissionTo('appointments.perform')) {
                    throw ValidationException::withMessages(['items' => 'Persona asignada no disponible.']);
                }
                $service = $services->get((int) $input['service_id']);
                $preserve = $old && $old->service_id === (int) $input['service_id'];
                if (! $preserve && ! $service?->is_active) {
                    throw ValidationException::withMessages(['items' => 'Servicio no disponible.']);
                }
                $duration = (int) $input['duration_minutes'];
                $quantity = (int) $input['quantity'];
                $end = $cursor->addMinutes($duration * $quantity);
                if (! BusinessHours::contains($cursor, $end) || EmployeeAvailability::hasBreak($assigned, $cursor, $end)) {
                    throw ValidationException::withMessages(['start_time' => 'El horario invade comida o queda fuera del horario comercial.']);
                }
                if (AppointmentItem::where('assigned_to', $assigned)->where('scheduled_start', '<', $end->utc())->where('scheduled_end', '>', $cursor->utc())->whereHas('appointment', fn ($q) => $q->where('status', 'scheduled')->whereKeyNot($locked->id))->exists()) {
                    throw ValidationException::withMessages(['start_time' => 'El cambio se superpone con otra cita.']);
                }
                $price = $preserve ? $old->unit_price : $service->price;
                $line = $old ?? new AppointmentItem;
                $line->forceFill(['appointment_id' => $locked->id, 'service_id' => $preserve ? $old->service_id : $service->id, 'service_name' => $preserve ? $old->service_name : $service->name, 'service_description' => $preserve ? $old->service_description : $service->description, 'assigned_to' => $assigned, 'duration_minutes' => $duration, 'default_duration_minutes' => $preserve ? $old->default_duration_minutes : $service->duration_minutes, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => Money::fromCents(Money::toCents($price) * $quantity), 'position' => $position + 1, 'scheduled_start' => $cursor->utc(), 'scheduled_end' => $end->utc()]);
                $total += Money::toCents($price) * $quantity;
                $prepared[] = $line;
                $cursor = $end;
            }
            $locked->items()->whereNotIn('id', $seen)->delete();
            foreach ($prepared as $line) {
                $line->save();
            }
            $locked->forceFill(['client_name' => $data['client_name'], 'client_phone' => $data['client_phone'] ?? null, 'notes' => $data['notes'] ?? null, 'scheduled_start' => $start->utc(), 'scheduled_end' => $cursor->utc(), 'expected_duration_minutes' => $start->diffInMinutes($cursor), 'expected_total' => Money::fromCents($total), 'assigned_to' => $prepared[0]->assigned_to])->save();
            $event = new AppointmentEvent;
            $event->forceFill(['appointment_id' => $locked->id, 'type' => 'updated', 'performed_by' => $actor->id, 'occurred_at' => now('UTC'), 'previous_values' => $before, 'new_values' => ['items' => $locked->items()->get()->toArray(), 'total' => $locked->expected_total], 'notes' => 'Servicios editados. El adelanto se conserva; cualquier excedente requiere resolución explícita.'])->save();

            return $locked;
        }, 3);
    }
}
