<?php

namespace Tests\Feature;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4BAppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-20 14:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_and_administrator_can_cancel_visible_scheduled_appointments(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $first = $this->appointment($owner, [$employee], '10:00');
        $second = $this->appointment($owner, [$employee], '12:00');

        $this->actingAs($owner)->post("/appointments/{$first->id}/cancel", ['reason' => 'La clienta solicitó cancelar.'])->assertStatus(303);
        $this->actingAs($administrator)->post("/appointments/{$second->id}/cancel", ['reason' => 'Cambio solicitado por la clienta.'])->assertStatus(303);

        $this->assertSame(Appointment::STATUS_CANCELED, $first->fresh()->status);
        $this->assertSame(Appointment::STATUS_CANCELED, $second->fresh()->status);
    }

    public function test_employee_can_cancel_only_a_completely_owned_appointment(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $own = $this->appointment($employee, [$employee], '10:00');
        $shared = $this->appointment($owner, [$employee, $other], '12:00');

        $this->actingAs($employee)->post("/appointments/{$own->id}/cancel", ['reason' => 'Cancelación confirmada por la clienta.'])->assertStatus(303);
        $this->post("/appointments/{$shared->id}/cancel", ['reason' => 'Intento sobre cita compartida.'])->assertSessionHasErrors('appointment');

        $this->assertSame(Appointment::STATUS_CANCELED, $own->fresh()->status);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $shared->fresh()->status);
    }

    public function test_user_without_cancel_permission_receives_forbidden(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$employee], '10:00');
        Role::findByName('employee')->revokePermissionTo(Permissions::APPOINTMENTS_CANCEL);

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Motivo autorizado.'])->assertForbidden();
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
    }

    public function test_cancellation_requires_a_reason_between_five_and_five_hundred_characters(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", [])->assertSessionHasErrors('reason');
        $this->post("/appointments/{$appointment->id}/cancel", ['reason' => 'No'])->assertSessionHasErrors('reason');
        $this->post("/appointments/{$appointment->id}/cancel", ['reason' => str_repeat('x', 501)])->assertSessionHasErrors('reason');
    }

    public function test_cancellation_records_actor_time_reason_and_append_only_event(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');
        Carbon::setTestNow('2026-07-21 15:30:00 UTC');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'La clienta informó que no podrá asistir.'])->assertStatus(303);

        $appointment->refresh();
        $this->assertSame($owner->id, $appointment->canceled_by);
        $this->assertSame('2026-07-21 15:30:00', $appointment->canceled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('La clienta informó que no podrá asistir.', $appointment->cancellation_reason);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'type' => AppointmentEvent::TYPE_CANCELED,
            'performed_by' => $owner->id,
            'notes' => 'La clienta informó que no podrá asistir.',
        ]);
    }

    public function test_only_scheduled_can_transition_and_a_second_transition_is_rejected(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Primera transición válida.'])->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Segundo intento inválido.'])->assertSessionHasErrors('appointment');
        $this->post("/appointments/{$appointment->id}/no-show", ['reason' => 'Intento de cambiar estado terminal.'])->assertSessionHasErrors('appointment');

        $this->assertSame(Appointment::STATUS_CANCELED, $appointment->fresh()->status);
        $this->assertSame(1, $appointment->events()->whereIn('type', [AppointmentEvent::TYPE_CANCELED, AppointmentEvent::TYPE_NO_SHOW])->count());
    }

    public function test_owner_and_employee_can_mark_eligible_own_appointments_as_no_show(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $first = $this->appointment($owner, [$employee], '10:00');
        $second = $this->appointment($employee, [$employee], '12:00');
        Carbon::setTestNow('2026-07-21 19:00:00 UTC');

        $this->actingAs($owner)->post("/appointments/{$first->id}/no-show", ['reason' => 'No se presentó ni respondió.'])->assertStatus(303);
        $this->actingAs($employee)->post("/appointments/{$second->id}/no-show", ['reason' => 'La clienta no se presentó.'])->assertStatus(303);

        $this->assertSame(Appointment::STATUS_NO_SHOW, $first->fresh()->status);
        $this->assertSame(Appointment::STATUS_NO_SHOW, $second->fresh()->status);
    }

    public function test_administrator_can_mark_a_visible_appointment_as_no_show(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');
        Carbon::setTestNow('2026-07-21 17:00:00 UTC');

        $this->actingAs($administrator)
            ->post("/appointments/{$appointment->id}/no-show", ['reason' => 'La clienta no se presentó.'])
            ->assertStatus(303);

        $this->assertSame(Appointment::STATUS_NO_SHOW, $appointment->fresh()->status);
    }

    public function test_user_without_no_show_permission_receives_forbidden(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$employee], '10:00');
        Role::findByName('employee')->revokePermissionTo(Permissions::APPOINTMENTS_MARK_NO_SHOW);
        Carbon::setTestNow('2026-07-21 17:00:00 UTC');

        $this->actingAs($employee)
            ->post("/appointments/{$appointment->id}/no-show", ['reason' => 'La clienta no se presentó.'])
            ->assertForbidden();

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
    }

    public function test_employee_cannot_mark_a_shared_appointment_as_no_show(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $shared = $this->appointment($owner, [$employee, $this->user('employee')], '10:00');
        Carbon::setTestNow('2026-07-21 17:00:00 UTC');

        $this->actingAs($employee)->post("/appointments/{$shared->id}/no-show", ['reason' => 'Intento sobre cita compartida.'])->assertSessionHasErrors('appointment');
        $this->assertSame(Appointment::STATUS_SCHEDULED, $shared->fresh()->status);
    }

    public function test_no_show_is_rejected_before_start_and_allowed_at_start(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');
        Carbon::setTestNow('2026-07-21 15:59:59 UTC');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/no-show", ['reason' => 'Aún no corresponde marcar ausencia.'])->assertSessionHasErrors('appointment');
        Carbon::setTestNow('2026-07-21 16:00:00 UTC');
        $this->post("/appointments/{$appointment->id}/no-show", ['reason' => 'La hora llegó y no se presentó.'])->assertStatus(303);

        $this->assertSame(Appointment::STATUS_NO_SHOW, $appointment->fresh()->status);
    }

    public function test_no_show_requires_reason_and_records_actor_time_and_event(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$employee], '10:00');
        Carbon::setTestNow('2026-07-21 16:15:00 UTC');

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/no-show", [])->assertSessionHasErrors('reason');
        $this->post("/appointments/{$appointment->id}/no-show", ['reason' => 'La clienta no llegó a la cita.'])->assertStatus(303);

        $appointment->refresh();
        $this->assertSame($employee->id, $appointment->no_show_by);
        $this->assertSame('2026-07-21 16:15:00', $appointment->no_show_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('La clienta no llegó a la cita.', $appointment->no_show_reason);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'type' => AppointmentEvent::TYPE_NO_SHOW, 'performed_by' => $employee->id]);
    }

    public function test_terminal_appointments_release_availability(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service();
        $canceled = $this->appointment($owner, [$employee], '10:00', $service);
        $noShow = $this->appointment($owner, [$employee], '12:00', $service);
        $completed = $this->appointment($owner, [$employee], '14:00', $service);
        Carbon::setTestNow('2026-07-21 19:00:00 UTC');
        $this->actingAs($owner)->post("/appointments/{$canceled->id}/cancel", ['reason' => 'Cancelación que libera horario.'])->assertStatus(303);
        $this->post("/appointments/{$noShow->id}/no-show", ['reason' => 'Ausencia que libera horario.'])->assertStatus(303);
        $completed->status = Appointment::STATUS_COMPLETED;
        $completed->save();

        Carbon::setTestNow('2026-07-20 14:00:00 UTC');
        $response = $this->postJson('/appointments/availability', [
            'date' => '2026-07-21',
            'items' => [['service_id' => $service->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 60]],
        ])->assertOk();
        $this->assertContains('10:00', $response->json('available_times'));
        $this->assertContains('12:00', $response->json('available_times'));
        $this->assertContains('14:00', $response->json('available_times'));
    }

    public function test_terminal_appointments_cannot_be_edited_reprogrammed_or_reactivated(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$employee], '10:00');
        $this->actingAs($employee)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Estado terminal definitivo.'])->assertStatus(303);

        $this->put("/appointments/{$appointment->id}", ['client_name' => 'Cambio', 'client_phone' => null, 'notes' => null])->assertSessionHasErrors('appointment');
        $this->post("/appointments/{$appointment->id}/reschedule", ['date' => '2026-07-22', 'start_time' => '10:00'])->assertSessionHasErrors('appointment');
        $this->assertFalse(Route::has('appointments.reactivate'));
        $this->assertSame(Appointment::STATUS_CANCELED, $appointment->fresh()->status);
    }

    public function test_terminal_event_failure_rolls_back_the_status_change(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_CANCELED
            ? throw new RuntimeException('Fallo terminal inducido')
            : null);

        try {
            app(CancelAppointmentAction::class)->execute($owner, $appointment, 'Debe revertirse por completo.');
            $this->fail('La transición debía propagar el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo terminal inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertNull($appointment->canceled_at);
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id, 'type' => AppointmentEvent::TYPE_CANCELED]);
    }

    public function test_daily_cards_receive_backend_action_flags_for_owned_and_shared_appointments(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $own = $this->appointment($owner, [$employee], '10:00');
        $this->appointment($owner, [$employee, $other], '12:00');
        Carbon::setTestNow('2026-07-21 19:00:00 UTC');

        $this->actingAs($owner)->get('/appointments?view=day&date=2026-07-21')
            ->assertInertia(fn ($page) => $page
                ->where('appointments.0.id', $own->id)
                ->where('appointments.0.can_reschedule', true)
                ->where('appointments.0.can_change_status', true)
                ->where('appointments.0.can_mark_no_show_now', true));

        $this->actingAs($employee)->get('/appointments?view=day&date=2026-07-21')
            ->assertInertia(fn ($page) => $page
                ->where('appointments.1.is_shared', true)
                ->where('appointments.1.can_reschedule', false)
                ->where('appointments.1.can_change_status', false));

        $this->actingAs($employee)->put("/appointments/{$own->id}", [
            'client_name' => 'Clienta propia actualizada',
            'client_phone' => null,
            'notes' => null,
        ])->assertStatus(303);
        $shared = Appointment::query()->where('id', '!=', $own->id)->firstOrFail();
        $this->put("/appointments/{$shared->id}", [
            'client_name' => 'Cambio no permitido',
            'client_phone' => null,
            'notes' => null,
        ])->assertSessionHasErrors('appointment');
    }

    public function test_daily_agenda_includes_only_scheduled_for_view_all_and_employee(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $scheduled = $this->appointment($owner, [$employee], '09:00');
        $terminal = [
            [$this->appointment($owner, [$employee], '10:00'), Appointment::STATUS_CANCELED],
            [$this->appointment($owner, [$employee], '11:00'), Appointment::STATUS_NO_SHOW],
            [$this->appointment($owner, [$employee], '12:00'), Appointment::STATUS_COMPLETED],
        ];
        foreach ($terminal as [$appointment, $status]) {
            $appointment->status = $status;
            $appointment->save();
        }

        foreach ([$owner, $employee] as $viewer) {
            $this->actingAs($viewer)->get('/appointments?view=day&date=2026-07-21')
                ->assertInertia(fn ($page) => $page
                    ->has('appointments', 1)
                    ->where('appointments.0.id', $scheduled->id)
                    ->where('appointments.0.status', Appointment::STATUS_SCHEDULED));
        }
    }

    public function test_cancel_and_no_show_return_to_the_same_agenda_and_remove_cards_immediately(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $canceled = $this->appointment($owner, [$employee], '10:00');
        $noShow = $this->appointment($owner, [$employee], '12:00');
        $url = "/appointments?view=day&month=2026-07&date=2026-07-21&employee_id={$employee->id}";
        Carbon::setTestNow('2026-07-21 19:00:00 UTC');

        $this->actingAs($owner)->from($url)
            ->post("/appointments/{$canceled->id}/cancel", ['reason' => 'Cancelación conservada para auditoría.'])
            ->assertRedirect($url)
            ->assertSessionHas('success', 'La cita fue cancelada correctamente.');
        $this->get($url)->assertInertia(fn ($page) => $page
            ->where('view', 'day')
            ->where('month', '2026-07')
            ->where('date', '2026-07-21')
            ->where('employee_id', $employee->id)
            ->has('appointments', 1)
            ->where('appointments.0.id', $noShow->id));

        $this->from($url)
            ->post("/appointments/{$noShow->id}/no-show", ['reason' => 'Ausencia conservada para auditoría.'])
            ->assertRedirect($url)
            ->assertSessionHas('success', 'La cita fue marcada como No llegó.');
        $this->get($url)->assertInertia(fn ($page) => $page
            ->where('view', 'day')
            ->where('month', '2026-07')
            ->where('date', '2026-07-21')
            ->where('employee_id', $employee->id)
            ->has('appointments', 0));

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_items', 2);
        $this->assertSame(2, AppointmentEvent::query()->whereIn('type', [AppointmentEvent::TYPE_CANCELED, AppointmentEvent::TYPE_NO_SHOW])->count());
        $this->getJson("/appointments/{$canceled->id}")
            ->assertOk()
            ->assertJsonPath('appointment.status_reason', 'Cancelación conservada para auditoría.')
            ->assertJsonPath('appointment.events.0.type', AppointmentEvent::TYPE_CANCELED);
        $this->getJson("/appointments/{$noShow->id}")
            ->assertOk()
            ->assertJsonPath('appointment.status_reason', 'Ausencia conservada para auditoría.')
            ->assertJsonPath('appointment.events.0.type', AppointmentEvent::TYPE_NO_SHOW);
    }

    public function test_detail_exposes_human_terminal_history_and_terminal_actions_disappear(): void
    {
        $owner = $this->user('owner', ['name' => 'Responsable']);
        $appointment = $this->appointment($owner, [$this->user('employee')], '10:00');
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Motivo visible y entendible.'])->assertStatus(303);

        $this->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.status_label', 'Cancelada')
            ->assertJsonPath('appointment.status_reason', 'Motivo visible y entendible.')
            ->assertJsonPath('appointment.status_changed_by.name', 'Responsable')
            ->assertJsonPath('appointment.events.0.type_label', 'Cita cancelada')
            ->assertJsonPath('appointment.events.0.notes', 'Motivo visible y entendible.')
            ->assertJsonPath('appointment.events.0.changes.0.new', 'Cancelada');
    }

    public function test_permissions_routes_and_phase_boundary_are_exact_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        foreach ([Permissions::APPOINTMENTS_CANCEL, Permissions::APPOINTMENTS_MARK_NO_SHOW] as $permission) {
            $this->assertSame(1, Permission::query()->where('name', $permission)->count());
            $this->assertTrue(Role::findByName('owner')->hasPermissionTo($permission));
            $this->assertTrue(Role::findByName('administrator')->hasPermissionTo($permission));
            $this->assertTrue(Role::findByName('employee')->hasPermissionTo($permission));
        }
        $this->assertTrue(Route::has('appointments.cancel'));
        $this->assertTrue(Route::has('appointments.no-show'));
        $this->assertTrue(Route::has('appointments.deposit'));
        $this->assertFalse(Route::has('appointments.refund'));
        $this->assertTrue(Route::has('appointments.checkout'));
    }

    private function appointment(User $actor, array $assignees, string $time, ?Service $service = null): Appointment
    {
        $service ??= $this->service();

        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => 'María López',
            'date' => '2026-07-21',
            'start_time' => $time,
            'items' => collect($assignees)->values()->map(fn (User $assignee) => [
                'service_id' => $service->id,
                'assigned_to' => $assignee->id,
                'quantity' => 1,
                'duration_minutes' => 60,
            ])->all(),
        ]);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function service(): Service
    {
        return Service::query()->create([
            'name' => 'Facial '.fake()->unique()->numerify('###'),
            'duration_minutes' => 60,
            'price' => '100.00',
            'is_active' => true,
        ]);
    }
}
