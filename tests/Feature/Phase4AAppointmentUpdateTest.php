<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4AAppointmentUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-20 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_employee_detail_exposes_only_their_visible_segment(): void
    {
        $employee = $this->user('employee', ['name' => 'Especialista']);
        $appointment = $this->appointment($employee, $employee, $this->service());

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.client_name', 'María López')
            ->assertJsonPath('appointment.events.0.type', AppointmentEvent::TYPE_CREATED)
            ->assertJsonCount(1, 'appointment.visible_items')
            ->assertJsonMissingPath('appointment.items')
            ->assertJsonPath('appointment.visible_items.0.assigned_to.name', 'Especialista');
    }

    public function test_employee_cannot_view_or_update_another_employees_appointment(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();
        $appointment = $this->appointment($owner, $other, $service);

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")->assertForbidden();
        $this->put("/appointments/{$appointment->id}", $this->updatePayload($other, $service))->assertForbidden();
        $this->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($other, $service))->assertForbidden();
    }

    public function test_owner_and_administrator_with_view_all_can_view_any_appointment(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, $employee, $this->service());

        $this->actingAs($owner)->getJson("/appointments/{$appointment->id}")->assertOk();
        $this->actingAs($administrator)->getJson("/appointments/{$appointment->id}")->assertOk();
    }

    public function test_employee_can_edit_their_own_scheduled_appointment(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $appointment = $this->appointment($employee, $employee, $service);

        $this->actingAs($employee)->put("/appointments/{$appointment->id}", $this->updatePayload($employee, $service, [
            'client_name' => 'Clienta actualizada',
            'client_phone' => null,
            'notes' => 'Nueva nota',
        ]))->assertStatus(303);

        $appointment->refresh();
        $this->assertSame('Clienta actualizada', $appointment->client_name);
        $this->assertNull($appointment->client_phone);
        $this->assertSame('Nueva nota', $appointment->notes);
    }

    public function test_employee_cannot_change_assigned_to_with_a_fabricated_request(): void
    {
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();
        $appointment = $this->appointment($employee, $employee, $service);

        $payload = $this->reschedulePayload($other, $service);
        $payload['assignments'] = [['appointment_item_id' => $appointment->items()->firstOrFail()->id, 'assigned_to' => $other->id]];
        $this->actingAs($employee)->post("/appointments/{$appointment->id}/reschedule", $payload)->assertSessionHasErrors('assignments');

        $this->assertSame($employee->id, $appointment->fresh()->assigned_to);
    }

    public function test_owner_can_reassign_an_appointment(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service();
        $appointment = $this->appointment($owner, $first, $service);

        $payload = $this->reschedulePayload($second, $service);
        $payload['assignments'] = [['appointment_item_id' => $appointment->items()->firstOrFail()->id, 'assigned_to' => $second->id]];
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/reschedule", $payload)
            ->assertStatus(303);

        $this->assertSame($second->id, $appointment->fresh()->assigned_to);
    }

    public function test_administrator_with_assign_can_reassign_while_rescheduling(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service();
        $appointment = $this->appointment($owner, $first, $service);

        $this->actingAs($administrator)->post(
            "/appointments/{$appointment->id}/reschedule",
            [...$this->reschedulePayload($second, $service, ['start_time' => '13:00']), 'assignments' => [['appointment_item_id' => $appointment->items()->firstOrFail()->id, 'assigned_to' => $second->id]]],
        )->assertStatus(303);

        $this->assertSame($second->id, $appointment->fresh()->assigned_to);
    }

    public function test_only_scheduled_appointments_can_be_updated_or_rescheduled(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service();

        foreach ([Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW] as $index => $status) {
            $appointment = $this->appointment($owner, $employee, $service, ['start_time' => sprintf('%02d:00', 10 + $index)]);
            $appointment->status = $status;
            $appointment->save();

            $this->actingAs($owner)->put("/appointments/{$appointment->id}", $this->updatePayload($employee, $service))
                ->assertSessionHasErrors('appointment');
            $this->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($employee, $service))
                ->assertSessionHasErrors('appointment');
        }
    }

    public function test_rescheduling_recalculates_end_time_without_changing_reserved_services(): void
    {
        $employee = $this->user('employee');
        $original = $this->service(['duration_minutes' => 45, 'price' => '100.00']);
        $appointment = $this->appointment($employee, $employee, $original);
        $payload = $this->reschedulePayload($employee, $original, [
            'date' => '2026-07-22',
            'start_time' => '14:15',
            'expected_total' => '0.01',
            'expected_duration_minutes' => 1,
            'scheduled_end' => '2030-01-01T00:00:00Z',
        ]);
        $payload['services'] = [['service_id' => $this->service(['name' => 'Fabricado'])->id, 'quantity' => 2]];

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/reschedule", $payload)->assertStatus(303);

        $appointment->refresh();
        $this->assertSame('2026-07-22 20:15:00', $appointment->scheduled_start->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-22 21:00:00', $appointment->scheduled_end->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(45, $appointment->expected_duration_minutes);
        $this->assertSame('100.00', $appointment->expected_total);
        $this->assertDatabaseHas('appointment_items', ['appointment_id' => $appointment->id, 'service_id' => $original->id, 'quantity' => 1]);
    }

    public function test_editing_ignores_fabricated_services_and_keeps_snapshots(): void
    {
        $employee = $this->user('employee');
        $original = $this->service(['name' => 'Original']);
        $replacement = $this->service([
            'name' => 'Nuevo servicio',
            'description' => 'Nueva descripción',
            'duration_minutes' => 45,
            'price' => '199.99',
        ]);
        $appointment = $this->appointment($employee, $employee, $original);

        $this->actingAs($employee)->put(
            "/appointments/{$appointment->id}",
            $this->updatePayload($employee, $replacement),
        )->assertStatus(303);

        $this->assertDatabaseCount('appointment_items', 1);
        $item = AppointmentItem::query()->where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame($original->id, $item->service_id);
        $this->assertSame('Original', $item->service_name);
        $this->assertSame('Cuidado y esmaltado', $item->service_description);
        $this->assertSame(45, $item->duration_minutes);
        $this->assertSame('250.00', $item->unit_price);
        $event = AppointmentEvent::query()->where('type', AppointmentEvent::TYPE_UPDATED)->firstOrFail();
        $this->assertArrayNotHasKey('services', $event->new_values);
    }

    public function test_basic_edit_preserves_service_snapshots_when_catalog_changes(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 45, 'price' => '250.00']);
        $appointment = $this->appointment($employee, $employee, $service);
        $service->update(['name' => 'Catálogo nuevo', 'duration_minutes' => 90, 'price' => '900.00']);

        $this->actingAs($employee)->put("/appointments/{$appointment->id}", $this->updatePayload($employee, $service, [
            'client_name' => 'Solo cambia la clienta',
        ]))->assertStatus(303);

        $appointment->refresh();
        $item = $appointment->items()->firstOrFail();
        $this->assertSame('Manicura', $item->service_name);
        $this->assertSame(45, $item->duration_minutes);
        $this->assertSame('250.00', $item->unit_price);
        $this->assertSame('250.00', $appointment->expected_total);
        $this->assertSame(45, $appointment->expected_duration_minutes);
    }

    public function test_inactive_or_non_performer_assignee_is_rejected(): void
    {
        $owner = $this->user('owner');
        $current = $this->user('employee');
        $inactive = $this->user('employee', ['is_active' => false]);
        $administrator = $this->user('administrator');
        $service = $this->service();
        $appointment = $this->appointment($owner, $current, $service);

        $payload = $this->reschedulePayload($inactive, $service);
        $payload['assignments'] = [['appointment_item_id' => $appointment->items()->firstOrFail()->id, 'assigned_to' => $inactive->id]];
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/reschedule", $payload)->assertSessionHasErrors('items');
        $payload['assignments'][0]['assigned_to'] = $administrator->id;
        $this->post("/appointments/{$appointment->id}/reschedule", $payload)->assertSessionHasErrors('items');
        $this->assertSame($current->id, $appointment->fresh()->assigned_to);
    }

    public function test_past_invalid_interval_and_cross_midnight_reschedules_are_rejected(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $appointment = $this->appointment($employee, $employee, $service);

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'date' => '2026-07-20',
            'start_time' => '07:45',
        ]))->assertSessionHasErrors('date');
        $this->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '10:10',
        ]))->assertSessionHasErrors('start_time');
        $this->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '23:30',
        ]))->assertSessionHasErrors('start_time');
    }

    public function test_reschedule_rejects_overlap_but_excludes_itself(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $first = $this->appointment($employee, $employee, $service, ['start_time' => '10:00']);
        $second = $this->appointment($employee, $employee, $service, ['start_time' => '12:00']);

        $this->actingAs($employee)->post("/appointments/{$second->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '10:30',
        ]))->assertSessionHasErrors('start_time');

        $this->post("/appointments/{$first->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '10:00',
        ]))->assertStatus(303);
    }

    public function test_reschedule_allows_an_appointment_adjacent_to_another(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $first = $this->appointment($employee, $employee, $service, ['start_time' => '10:00']);
        $second = $this->appointment($employee, $employee, $service, ['start_time' => '12:00']);

        $this->actingAs($employee)->post("/appointments/{$second->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '11:00',
        ]))->assertStatus(303);

        $this->assertSame('2026-07-21 17:00:00', $second->fresh()->scheduled_start->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('appointments', ['id' => $first->id]);
    }

    public function test_failure_while_recording_event_rolls_back_appointment_items_and_event(): void
    {
        $employee = $this->user('employee');
        $original = $this->service(['name' => 'Original']);
        $replacement = $this->service(['name' => 'Reemplazo']);
        $appointment = $this->appointment($employee, $employee, $original);
        $originalStart = $appointment->scheduled_start;
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_RESCHEDULED
            ? throw new RuntimeException('Fallo de auditoría')
            : null);

        try {
            app(RescheduleAppointmentAction::class)->execute(
                $employee,
                $appointment,
                $this->reschedulePayload($employee, $replacement, ['start_time' => '14:00']),
            );
            $this->fail('La reprogramación debía propagar el fallo inducido.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $appointment->refresh();
        $this->assertTrue($appointment->scheduled_start->equalTo($originalStart));
        $this->assertDatabaseHas('appointment_items', [
            'appointment_id' => $appointment->id,
            'service_id' => $original->id,
            'service_name' => 'Original',
        ]);
        $this->assertDatabaseMissing('appointment_items', ['appointment_id' => $appointment->id, 'service_id' => $replacement->id]);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_updated_and_rescheduled_events_store_controlled_differences(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 45]);
        $appointment = $this->appointment($employee, $employee, $service);

        $this->actingAs($employee)->put("/appointments/{$appointment->id}", $this->updatePayload($employee, $service, [
            'client_name' => 'Nombre nuevo',
        ]))->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/reschedule", $this->reschedulePayload($employee, $service, [
            'start_time' => '13:15',
            'reschedule_note' => 'Solicitud de la clienta',
        ]))->assertStatus(303);

        $updated = AppointmentEvent::query()->where('type', AppointmentEvent::TYPE_UPDATED)->firstOrFail();
        $rescheduled = AppointmentEvent::query()->where('type', AppointmentEvent::TYPE_RESCHEDULED)->firstOrFail();
        $this->assertSame('María López', $updated->previous_values['client_name']);
        $this->assertSame('Nombre nuevo', $updated->new_values['client_name']);
        $this->assertArrayNotHasKey('permissions', $updated->new_values);
        $this->assertSame('Solicitud de la clienta', $rescheduled->notes);
        $this->assertArrayHasKey('scheduled_start', $rescheduled->previous_values);
        $this->assertArrayHasKey('scheduled_start', $rescheduled->new_values);
    }

    public function test_detail_resource_exposes_human_readable_changes_without_technical_json(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee', ['name' => 'Melany Lemus']);
        $second = $this->user('employee', ['name' => 'César Martínez']);
        $service = $this->service();
        $appointment = $this->appointment($owner, $first, $service);

        $this->actingAs($owner)->put("/appointments/{$appointment->id}", $this->updatePayload($first, $service, [
            'client_name' => 'María Ana López',
            'client_phone' => null,
            'notes' => 'Primera visita, retirar gel',
        ]))->assertStatus(303);
        $payload = $this->reschedulePayload($second, $service, [
            'date' => '2026-07-22',
            'start_time' => '14:30',
            'reschedule_note' => 'La clienta solicitó cambio de hora',
        ]);
        $payload['assignments'] = [['appointment_item_id' => $appointment->items()->firstOrFail()->id, 'assigned_to' => $second->id]];
        $this->post("/appointments/{$appointment->id}/reschedule", $payload)->assertStatus(303);

        $this->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.events.0.type_label', 'Cita reprogramada')
            ->assertJsonPath('appointment.events.0.changes.0.label', 'Manicura')
            ->assertJsonPath('appointment.events.0.changes.0.previous', 'Melany Lemus')
            ->assertJsonPath('appointment.events.0.changes.0.new', 'César Martínez')
            ->assertJsonPath('appointment.events.0.notes', 'La clienta solicitó cambio de hora')
            ->assertJsonPath('appointment.events.1.type_label', 'Información actualizada')
            ->assertJsonPath('appointment.events.1.changes.0.label', 'Nombre')
            ->assertJsonMissingPath('appointment.events.1.changes.0.service_id')
            ->assertJsonMissingPath('appointment.events.1.summary');
    }

    public function test_appointment_events_are_append_only(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, $employee, $this->service());
        $event = $appointment->events()->firstOrFail();

        try {
            $event->notes = 'Alterada';
            $event->save();
            $this->fail('El evento no debía actualizarse.');
        } catch (LogicException $exception) {
            $this->assertSame('Los eventos de cita son inmutables.', $exception->getMessage());
        }

        try {
            $event->delete();
            $this->fail('El evento no debía eliminarse.');
        } catch (LogicException $exception) {
            $this->assertSame('Los eventos de cita son inmutables.', $exception->getMessage());
        }

        $this->assertDatabaseHas('appointment_events', ['id' => $event->id]);
    }

    public function test_manipulated_financial_duration_state_creator_and_terminal_fields_are_ignored(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 30, 'price' => '150.00']);
        $appointment = $this->appointment($employee, $employee, $service);

        $this->actingAs($employee)->put("/appointments/{$appointment->id}", $this->updatePayload($employee, $service, [
            'expected_total' => '0.01',
            'expected_duration_minutes' => 1,
            'scheduled_end' => '2030-01-01T00:00:00Z',
            'status' => Appointment::STATUS_COMPLETED,
            'created_by' => 999999,
            'completed_at' => now(),
            'canceled_at' => now(),
            'no_show_at' => now(),
        ]))->assertStatus(303);

        $appointment->refresh();
        $this->assertSame('150.00', $appointment->expected_total);
        $this->assertSame(30, $appointment->expected_duration_minutes);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame($employee->id, $appointment->created_by);
        $this->assertNull($appointment->completed_at);
        $this->assertNull($appointment->canceled_at);
        $this->assertNull($appointment->no_show_at);
    }

    public function test_prompt_two_routes_exist_without_future_phase_routes(): void
    {
        $this->assertTrue(Route::has('appointments.show'));
        $this->assertTrue(Route::has('appointments.update'));
        $this->assertTrue(Route::has('appointments.reschedule'));
        $this->assertTrue(Route::has('appointments.cancel'));
        $this->assertTrue(Route::has('appointments.no-show'));
        $this->assertFalse(Route::has('appointments.deposit'));
        $this->assertFalse(Route::has('appointments.checkout'));
        $this->delete('/appointments/1')->assertStatus(405);
    }

    public function test_prompt_two_permissions_seed_idempotently_with_exact_role_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Permission::query()->where('name', Permissions::APPOINTMENTS_UPDATE)->count());
        $this->assertSame(1, Permission::query()->where('name', Permissions::APPOINTMENTS_ASSIGN)->count());
        $this->assertTrue(Role::findByName('owner')->hasAllPermissions([
            Permissions::APPOINTMENTS_UPDATE,
            Permissions::APPOINTMENTS_ASSIGN,
        ]));
        $this->assertTrue(Role::findByName('administrator')->hasAllPermissions([
            Permissions::APPOINTMENTS_UPDATE,
            Permissions::APPOINTMENTS_ASSIGN,
        ]));
        $this->assertTrue(Role::findByName('employee')->hasPermissionTo(Permissions::APPOINTMENTS_UPDATE));
        $this->assertFalse(Role::findByName('employee')->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN));
    }

    public function test_view_all_users_receive_all_segments_and_full_totals(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee', ['name' => 'Melany']);
        $other = $this->user('employee', ['name' => 'César']);
        $appointment = $this->sharedAppointment($owner, $employee, $other);

        foreach ([$owner, $administrator] as $viewer) {
            $this->actingAs($viewer)->getJson("/appointments/{$appointment->id}")
                ->assertOk()
                ->assertJsonCount(2, 'appointment.visible_items')
                ->assertJsonPath('appointment.visible_duration_minutes', 90)
                ->assertJsonPath('appointment.visible_total', '350.00')
                ->assertJsonPath('appointment.is_shared', true);
        }
    }

    public function test_employee_receives_only_own_segments_and_subtotal_without_other_employee_data(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee', ['name' => 'Melany']);
        $other = $this->user('employee', ['name' => 'César']);
        $appointment = $this->sharedAppointment($owner, $employee, $other);

        $this->actingAs($employee)->get("/appointments?view=day&date=2026-07-21")
            ->assertInertia(fn ($page) => $page
                ->has('appointments.0.visible_items', 1)
                ->where('appointments.0.visible_items.0.assigned_to.name', 'Melany')
                ->where('appointments.0.visible_duration_minutes', 60)
                ->where('appointments.0.visible_total', '100.00')
                ->where('appointments.0.is_shared', true)
                ->missing('appointments.0.items')
                ->missing('appointments.0.expected_total'));

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonCount(1, 'appointment.visible_items')
            ->assertJsonMissingPath('appointment.created_by')
            ->assertJsonMissingPath('appointment.visible_items.1')
            ->assertDontSee('César');
    }

    public function test_employee_segments_separated_by_another_person_remain_separate(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $first = $this->service(['name' => 'Facial', 'duration_minutes' => 60, 'price' => '100.00']);
        $middle = $this->service(['name' => 'Pedispa', 'duration_minutes' => 60, 'price' => '200.00']);
        $last = $this->service(['name' => 'Masaje', 'duration_minutes' => 60, 'price' => '300.00']);
        $appointment = app(CreateAppointmentAction::class)->execute($owner, [
            'client_name' => 'María López', 'date' => '2026-07-21', 'start_time' => '10:00',
            'items' => [
                ['service_id' => $first->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 60],
                ['service_id' => $middle->id, 'assigned_to' => $other->id, 'quantity' => 1, 'duration_minutes' => 60],
                ['service_id' => $last->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 60],
            ],
        ]);

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonCount(2, 'appointment.visible_items')
            ->assertJsonPath('appointment.visible_items.0.start_time', '10:00')
            ->assertJsonPath('appointment.visible_items.0.end_time', '11:00')
            ->assertJsonPath('appointment.visible_items.1.start_time', '12:00')
            ->assertJsonPath('appointment.visible_items.1.end_time', '13:00')
            ->assertJsonPath('appointment.visible_duration_minutes', 120)
            ->assertJsonPath('appointment.visible_total', '400.00');
    }

    public function test_employee_cannot_reprogram_a_shared_appointment_but_can_reprogram_their_own_one(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $shared = $this->sharedAppointment($owner, $employee, $other);
        $own = $this->appointment($employee, $employee, $this->service(['name' => 'Propio']), ['start_time' => '13:00']);

        $this->actingAs($employee)->post("/appointments/{$shared->id}/reschedule", $this->reschedulePayload($employee, $this->service(), ['start_time' => '15:00']))
            ->assertSessionHasErrors('appointment');
        $this->post("/appointments/{$own->id}/reschedule", $this->reschedulePayload($employee, $this->service(), ['start_time' => '15:00']))
            ->assertStatus(303);
        $this->actingAs($owner)->post("/appointments/{$shared->id}/reschedule", $this->reschedulePayload($employee, $this->service(), ['start_time' => '16:00']))
            ->assertStatus(303);
    }

    public function test_employee_history_hides_reassignments_that_only_affect_other_segments(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee', ['name' => 'Melany']);
        $other = $this->user('employee', ['name' => 'César']);
        $replacement = $this->user('employee', ['name' => 'Andrea']);
        $appointment = $this->sharedAppointment($owner, $employee, $other);
        $otherItem = $appointment->items()->where('assigned_to', $other->id)->firstOrFail();

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/reschedule", [
            'date' => '2026-07-22', 'start_time' => '14:00',
            'assignments' => [['appointment_item_id' => $otherItem->id, 'assigned_to' => $replacement->id]],
        ])->assertStatus(303);

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.events.0.changes', [])
            ->assertDontSee('César')
            ->assertDontSee('Andrea');
    }

    private function sharedAppointment(User $actor, User $first, User $second): Appointment
    {
        $firstService = $this->service(['name' => 'Facial', 'duration_minutes' => 60, 'price' => '100.00']);
        $secondService = $this->service(['name' => 'Pedispa', 'duration_minutes' => 30, 'price' => '250.00']);

        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => 'María López', 'client_phone' => '9999-9999', 'notes' => 'Nota general',
            'date' => '2026-07-21', 'start_time' => '10:00',
            'items' => [
                ['service_id' => $firstService->id, 'assigned_to' => $first->id, 'quantity' => 1, 'duration_minutes' => 60],
                ['service_id' => $secondService->id, 'assigned_to' => $second->id, 'quantity' => 1, 'duration_minutes' => 30],
            ],
        ]);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function service(array $attributes = []): Service
    {
        return Service::query()->create([
            'name' => 'Manicura',
            'description' => 'Cuidado y esmaltado',
            'duration_minutes' => 45,
            'price' => '250.00',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function appointment(User $actor, User $assignee, Service $service, array $overrides = []): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => $overrides['client_name'] ?? 'María López',
            'client_phone' => '9999-9999',
            'date' => $overrides['date'] ?? '2026-07-21',
            'start_time' => $overrides['start_time'] ?? '10:00',
            'items' => [[
                'service_id' => $service->id,
                'assigned_to' => $assignee->id,
                'quantity' => 1,
                'duration_minutes' => $service->duration_minutes,
            ]],
            'notes' => 'Nota original',
        ]);
    }

    private function updatePayload(User $assignee, Service $service, array $overrides = []): array
    {
        return [
            'client_name' => 'María López',
            'client_phone' => '9999-9999',
            'assigned_to' => $assignee->id,
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'notes' => 'Nota original',
            ...$overrides,
        ];
    }

    private function reschedulePayload(User $assignee, Service $service, array $overrides = []): array
    {
        return [
            'date' => '2026-07-21',
            'start_time' => '13:00',
            'reschedule_note' => null,
            ...$overrides,
        ];
    }
}
