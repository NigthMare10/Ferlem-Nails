<?php

namespace App\Actions\Loyalty;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardAppointmentService
{
    public function schedule(User $actor, LoyaltyRedemption $redemption, array $input): Appointment
    {
        abort_unless($actor->is_active && $actor->can('loyalty.manage') && $actor->can('loyalty.redeem') && $actor->can('appointments.create') && $actor->can('appointments.access'), 403);
        app(LoyaltyService::class)->expireDue();

        return DB::transaction(function () use ($actor, $redemption, $input) {
            $customer = Customer::whereKey($redemption->customer_id)->lockForUpdate()->firstOrFail();
            $r = LoyaltyRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($existing = $r->appointment()->first()) {
                return $existing;
            }
            if ($r->status !== 'reserved' || ! $r->expires_at->isFuture() || ! $customer->is_active || app(LoyaltyService::class)->balance($customer) < 0 || ($r->reward_snapshot['benefit_type'] ?? null) !== 'service') {
                throw ValidationException::withMessages(['redemption' => 'El canje no está disponible para agendar. Revisa su estado y el saldo de la clienta.']);
            }
            $service = Service::find($r->reward_snapshot['service_id'] ?? null);
            if (! $service?->is_active) {
                throw ValidationException::withMessages(['redemption' => 'El servicio del premio ya no está disponible. Revisa el canje antes de continuar.']);
            }
            $line = $input['items'][0];
            if ((int) $line['service_id'] !== $service->id || (int) $line['quantity'] !== 1 || ! empty($input['has_deposit'])) {
                throw ValidationException::withMessages(['items' => 'La cita debe contener solo una unidad del servicio canjeado y no admite adelantos.']);
            }
            $a = app(CreateAppointmentAction::class)->execute($actor, [...$input, 'client_name' => $customer->name, 'client_phone' => $customer->phone, 'has_deposit' => false, 'items' => [$line]], true);
            $a->forceFill(['customer_id' => $customer->id, 'loyalty_redemption_id' => $r->id])->save();
            $r->update(['status' => 'scheduled']);
            $this->audit($actor, 'reward.appointment_scheduled', $a, $r);

            return $a;
        }, 3);
    }

    public function complete(User $actor, Appointment $appointment): Appointment
    {
        abort_unless($actor->is_active && $actor->can('loyalty.redeem') && $actor->can('appointments.access'), 403);

        return DB::transaction(function () use ($actor, $appointment) {
            $customer = Customer::whereKey($appointment->customer_id)->lockForUpdate()->firstOrFail();
            $r = LoyaltyRedemption::whereKey($appointment->loyalty_redemption_id)->lockForUpdate()->firstOrFail();
            $a = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('appointments.view_all') || $a->items->every(fn ($i) => $i->assigned_to === $actor->id), 403);
            if ($a->status === 'completed' && $r->status === 'confirmed') {
                return $a;
            }
            if ($a->status !== 'scheduled' || $r->status !== 'scheduled' || $a->scheduled_start->isFuture() || ! $customer->is_active) {
                throw ValidationException::withMessages(['appointment' => 'No se puede completar este premio. Revisa el estado y espera a la hora de la cita.']);
            }
            $a->forceFill(['status' => 'completed', 'completed_at' => now('UTC')])->save();
            $r->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => 'Servicio gratuito completado en cita #'.$a->id]);
            $event = new AppointmentEvent;
            $event->forceFill(['appointment_id' => $a->id, 'type' => AppointmentEvent::TYPE_COMPLETED, 'performed_by' => $actor->id, 'occurred_at' => now('UTC'), 'previous_values' => ['status' => 'scheduled'], 'new_values' => ['status' => 'completed', 'redemption_id' => $r->id], 'notes' => 'Premio gratuito entregado. Sin venta, cobro ni puntos por compra.'])->save();
            $this->audit($actor, 'reward.appointment_completed', $a, $r);

            return $a;
        }, 3);
    }

    private function audit(User $actor, string $action, Appointment $a, LoyaltyRedemption $r): void
    {
        DB::table('security_audits')->insert(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'appointment', 'subject_id' => $a->id, 'after' => json_encode(['redemption_id' => $r->id, 'customer_id' => $a->customer_id, 'status' => $a->status]), 'created_at' => now(), 'updated_at' => now()]);
    }
}
