<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
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
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4AAppointmentTest extends TestCase
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

    public function test_guest_cannot_access_appointments(): void
    {
        $this->get('/appointments')->assertRedirect(route('login'));
        $this->post('/appointments')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_appointments(): void
    {
        $employee = $this->user('employee', ['is_active' => false]);

        $this->actingAs($employee)->get('/appointments')->assertRedirect(route('login'));
        $this->post('/appointments')->assertRedirect(route('login'));
    }

    public function test_user_without_appointments_access_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/appointments')->assertForbidden();
        $this->post('/appointments')->assertForbidden();
    }

    public function test_owner_sees_all_appointments_and_honduras_default_date(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee', ['name' => 'Ana']);
        $second = $this->user('employee', ['name' => 'Bea']);
        $service = $this->service();
        $this->createAppointment($owner, $first, $service, ['start_time' => '09:00']);
        $this->createAppointment($owner, $second, $service, ['start_time' => '10:00']);

        $this->actingAs($owner)->get('/appointments?view=day&date=2026-07-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Index')
                ->where('date', '2026-07-20')
                ->where('timezone', CreateAppointmentAction::TIMEZONE)
                ->has('appointments', 2)
                ->where('auth.navigation.appointments', true));
    }

    public function test_administrator_with_view_all_sees_all_appointments(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service();
        $this->createAppointment($owner, $first, $service, ['start_time' => '09:00']);
        $this->createAppointment($owner, $second, $service, ['start_time' => '10:00']);

        $this->actingAs($administrator)->get('/appointments?view=day&date=2026-07-20')
            ->assertInertia(fn (Assert $page) => $page->has('appointments', 2));
    }

    public function test_employee_only_receives_appointments_assigned_to_them(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee', ['name' => 'Propia']);
        $other = $this->user('employee', ['name' => 'Ajena']);
        $service = $this->service();
        $this->createAppointment($owner, $employee, $service, ['client_name' => 'Clienta propia', 'start_time' => '09:00']);
        $this->createAppointment($owner, $other, $service, ['client_name' => 'Clienta ajena', 'start_time' => '10:00']);

        $this->actingAs($employee)->get('/appointments?view=day&date=2026-07-20')
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.client_name', 'Clienta propia')
                ->has('assignees', 1)
                ->where('assignees.0.id', $employee->id));
    }

    public function test_employee_cannot_create_an_appointment_for_another_person(): void
    {
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();

        $this->actingAs($employee)->post('/appointments', $this->payload($other, $service))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_user_with_create_permission_can_create_an_appointment(): void
    {
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $service = $this->service();

        $this->actingAs($administrator)->post('/appointments', $this->payload($employee, $service))
            ->assertStatus(303)
            ->assertRedirect(route('appointments.index', ['date' => '2026-07-20']));

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_assignee_must_be_active(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee', ['is_active' => false]);
        $service = $this->service();

        $this->actingAs($owner)->post('/appointments', $this->payload($employee, $service))
            ->assertSessionHasErrors('items');
    }

    public function test_assignee_must_have_appointments_perform_permission(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $service = $this->service();

        $this->actingAs($owner)->post('/appointments', $this->payload($administrator, $service))
            ->assertSessionHasErrors('items');
    }

    public function test_only_active_services_can_be_used(): void
    {
        $employee = $this->user('employee');
        $inactive = $this->service(['is_active' => false]);

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $inactive))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_multiple_items_and_historical_snapshots_are_created(): void
    {
        $employee = $this->user('employee');
        $first = $this->service([
            'name' => 'Manicura clásica',
            'description' => 'Detalle original',
            'duration_minutes' => 45,
            'price' => '250.00',
        ]);
        $second = $this->service([
            'name' => 'Pedicura',
            'duration_minutes' => 60,
            'price' => '400.00',
        ]);
        $payload = $this->payload($employee, $first);
        $payload['items'][] = ['service_id' => $second->id, 'assigned_to' => $employee->id, 'quantity' => 2, 'duration_minutes' => $second->duration_minutes];

        $this->actingAs($employee)->post('/appointments', $payload)->assertStatus(303);

        $this->assertDatabaseCount('appointment_items', 2);
        $item = AppointmentItem::query()->where('service_id', $first->id)->firstOrFail();
        $this->assertSame('Manicura clásica', $item->service_name);
        $this->assertSame('Detalle original', $item->service_description);
        $this->assertSame(45, $item->duration_minutes);
        $this->assertSame('250.00', $item->unit_price);
        $this->assertSame('250.00', $item->line_total);

        $first->update(['name' => 'Nombre nuevo', 'duration_minutes' => 90, 'price' => '999.00']);
        $item->refresh();
        $this->assertSame('Manicura clásica', $item->service_name);
        $this->assertSame(45, $item->duration_minutes);
        $this->assertSame('250.00', $item->unit_price);
    }

    public function test_repeated_services_are_consolidated(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 30, 'price' => '100.00']);
        $payload = $this->payload($employee, $service);
        $payload['items'][] = ['service_id' => $service->id, 'assigned_to' => $employee->id, 'quantity' => 2, 'duration_minutes' => $service->duration_minutes];

        $this->actingAs($employee)->post('/appointments', $payload)->assertStatus(303);

        $this->assertDatabaseCount('appointment_items', 2);
        $this->assertEquals(3, AppointmentItem::query()->sum('quantity'));
        $this->assertEquals('300.00', AppointmentItem::query()->sum('line_total'));
    }

    public function test_end_time_duration_and_total_are_calculated_by_backend(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 45, 'price' => '125.50']);
        $payload = $this->payload($employee, $service, [
            'scheduled_end' => '2030-01-01T00:00:00Z',
            'expected_duration_minutes' => 1,
            'expected_total' => '0.01',
            'status' => Appointment::STATUS_COMPLETED,
            'created_by' => 999999,
        ]);
        $payload['items'][0]['quantity'] = 2;

        $this->actingAs($employee)->post('/appointments', $payload)->assertStatus(303);

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('2026-07-20 16:00:00', $appointment->scheduled_start->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 17:30:00', $appointment->scheduled_end->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(90, $appointment->expected_duration_minutes);
        $this->assertSame('251.00', $appointment->expected_total);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame($employee->id, $appointment->created_by);
    }

    public function test_appointment_in_the_past_is_rejected(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'start_time' => '07:45',
        ]))->assertSessionHasErrors('date');
    }

    public function test_start_time_must_use_fifteen_minute_intervals(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'start_time' => '10:10',
        ]))->assertSessionHasErrors('start_time');
    }

    public function test_appointment_cannot_cross_midnight(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'start_time' => '23:30',
        ]))->assertSessionHasErrors('start_time');
    }

    public function test_overlapping_appointment_is_rejected_for_same_assignee(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $this->createAppointment($employee, $employee, $service, ['start_time' => '10:00']);

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'start_time' => '10:30',
        ]))->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_same_time_is_allowed_for_different_assignees(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $this->createAppointment($owner, $first, $service, ['start_time' => '10:00']);

        $this->actingAs($owner)->post('/appointments', $this->payload($second, $service, [
            'start_time' => '10:00',
        ]))->assertStatus(303);

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_appointment_can_start_exactly_when_another_ends(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $this->createAppointment($employee, $employee, $service, ['start_time' => '10:00']);

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'start_time' => '11:00',
        ]))->assertStatus(303);

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_created_event_is_recorded_without_sensitive_data(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();

        $this->actingAs($employee)->post('/appointments', $this->payload($employee, $service, [
            'client_phone' => '9999-9999',
        ]))->assertStatus(303);

        $event = AppointmentEvent::query()->firstOrFail();
        $this->assertSame(AppointmentEvent::TYPE_CREATED, $event->type);
        $this->assertSame($employee->id, $event->performed_by);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $event->new_values['status']);
        $this->assertArrayNotHasKey('client_phone', $event->new_values);
        $this->assertArrayNotHasKey('permissions', $event->new_values);
        $this->assertNull($event->previous_values);
    }

    public function test_failure_after_event_creation_rolls_back_everything(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn () => throw new RuntimeException('Fallo inducido'));

        try {
            app(CreateAppointmentAction::class)->execute($employee, $this->payload($employee, $service));
            $this->fail('La acción debía propagar el fallo inducido.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_items', 0);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_appointment_seeders_are_idempotent_with_exact_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $permissions = [
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_VIEW_ALL,
            Permissions::APPOINTMENTS_CREATE,
            Permissions::APPOINTMENTS_PERFORM,
        ];

        $this->assertSame(5, Permission::query()->whereIn('name', $permissions)->count());
        $owner = Role::findByName('owner');
        $administrator = Role::findByName('administrator');
        $employee = Role::findByName('employee');
        $this->assertTrue($owner->hasAllPermissions($permissions));
        $this->assertTrue($administrator->hasAllPermissions([
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_VIEW_ALL,
            Permissions::APPOINTMENTS_CREATE,
        ]));
        $this->assertFalse($administrator->hasPermissionTo(Permissions::APPOINTMENTS_PERFORM));
        $this->assertTrue($employee->hasAllPermissions([
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_CREATE,
            Permissions::APPOINTMENTS_PERFORM,
        ]));
        $this->assertFalse($employee->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL));
    }

    public function test_appointment_routes_remain_limited_to_phase_4a(): void
    {
        $this->assertTrue(Route::has('appointments.index'));
        $this->assertTrue(Route::has('appointments.store'));
        $this->assertTrue(Route::has('appointments.show'));
        $this->assertTrue(Route::has('appointments.update'));
        $this->assertTrue(Route::has('appointments.reschedule'));
        $this->assertTrue(Route::has('appointments.cancel'));
        $this->assertTrue(Route::has('appointments.no-show'));
        $this->assertTrue(Route::has('appointments.deposit'));
        $this->assertTrue(Route::has('appointments.checkout'));
        $this->assertTrue(Route::has('appointments.history'));
        $this->delete('/appointments/1')->assertStatus(405);
    }

    public function test_appointments_cannot_be_physically_deleted(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->createAppointment($employee, $employee, $this->service());

        try {
            $appointment->delete();
            $this->fail('La cita no debía eliminarse físicamente.');
        } catch (LogicException $exception) {
            $this->assertSame('Las citas no pueden eliminarse físicamente.', $exception->getMessage());
        }

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
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

    private function payload(User $assignee, Service $service, array $overrides = []): array
    {
        return [
            'client_name' => 'María López',
            'client_phone' => '9999-9999',
            'date' => '2026-07-20',
            'start_time' => '10:00',
            'items' => [[
                'service_id' => $service->id,
                'assigned_to' => $assignee->id,
                'quantity' => 1,
                'duration_minutes' => $service->duration_minutes,
            ]],
            'notes' => 'Usar tono natural',
            ...$overrides,
        ];
    }

    private function createAppointment(User $actor, User $assignee, Service $service, array $overrides = []): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($actor, $this->payload($assignee, $service, $overrides));
    }
}
