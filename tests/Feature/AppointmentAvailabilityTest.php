<?php

namespace Tests\Feature;

use App\Actions\Appointments\BuildAppointmentAvailabilityAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentAvailabilityTest extends TestCase
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

    public function test_employee_can_only_query_and_create_segments_assigned_to_themself(): void
    {
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();
        $payload = $this->items($service, $other);

        try {
            app(BuildAppointmentAvailabilityAction::class)->execute($employee, '2026-07-21', $payload);
            $this->fail('La disponibilidad ajena debía rechazarse.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->actingAs($employee)->post('/appointments', ['client_name' => 'María', 'date' => '2026-07-21', 'start_time' => '14:00', 'items' => $payload])->assertSessionHasErrors('items');
        $this->actingAs($employee)->postJson('/appointments/availability', ['date' => '2026-07-21', 'items' => $this->items($service, $employee)])
            ->assertOk()->assertJsonPath('operating_open_time', '08:00')->assertJsonPath('operating_close_time', '18:00');
    }

    public function test_assign_users_can_query_availability_per_service(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service();
        $items = [$this->item($service, $first), $this->item($service, $second)];

        $this->actingAs($owner)->postJson('/appointments/availability', ['date' => '2026-07-21', 'items' => $items])->assertOk();
        $this->actingAs($administrator)->postJson('/appointments/availability', ['date' => '2026-07-21', 'items' => $items])->assertOk();
    }

    public function test_availability_uses_segments_conflicts_adjacency_and_operating_close(): void
    {
        $owner = $this->user('owner');
        $busy = $this->user('employee');
        $free = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $this->create($owner, $busy, $service, '15:00');

        $busyTimes = $this->available($owner, '2026-07-21', [$this->item($service, $busy)]);
        $freeTimes = $this->available($owner, '2026-07-21', [$this->item($service, $free)]);
        $longTimes = $this->available($owner, '2026-07-21', [$this->item($service, $free, 1, 240)]);

        $this->assertNotContains('15:00', $busyTimes);
        $this->assertContains('15:00', $freeTimes);
        $this->assertContains('14:00', $busyTimes);
        $this->assertNotContains('15:15', $longTimes);
        $this->assertNotContains('17:15', $busyTimes);
    }

    public function test_any_conflicting_segment_removes_the_whole_start_and_a_full_day_has_no_availability(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $this->create($owner, $second, $service, '15:00');

        $times = $this->available($owner, '2026-07-21', [$this->item($service, $first), $this->item($service, $second)]);
        $this->assertNotContains('14:00', $times);

        foreach (range(8, 17) as $hour) {
            $this->create($owner, $first, $service, sprintf('%02d:00', $hour));
        }
        $response = $this->actingAs($owner)->postJson('/appointments/availability', ['date' => '2026-07-21', 'items' => [$this->item($service, $first)]])
            ->assertOk()->assertJsonPath('has_availability', false);
        $this->assertSame([], $response->json('available_times'));
    }

    public function test_reprogramming_availability_excludes_its_own_segments(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service(['duration_minutes' => 60]);
        $appointment = $this->create($owner, $employee, $service, '15:00');
        $this->create($owner, $employee, $service, '16:00');

        $response = $this->actingAs($owner)->postJson('/appointments/availability', [
            'appointment_id' => $appointment->id,
            'date' => '2026-07-21',
            'assignments' => [[
                'appointment_item_id' => $appointment->items()->firstOrFail()->id,
                'assigned_to' => $employee->id,
            ]],
        ])->assertOk();
        $this->assertContains('15:00', $response->json('available_times'));
        $this->assertNotContains('16:00', $response->json('available_times'));
    }

    public function test_reprogramming_availability_returns_specific_validation_and_authorization_errors(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();
        $appointment = $this->create($owner, $employee, $service, '15:00');

        $this->actingAs($owner)->postJson('/appointments/availability', [
            'appointment_id' => $appointment->id,
        ])->assertUnprocessable()->assertJsonPath('errors.date.0', 'Selecciona una fecha.');

        $this->actingAs($other)->postJson('/appointments/availability', [
            'appointment_id' => $appointment->id,
            'date' => '2026-07-21',
        ])->assertForbidden();
    }

    public function test_employee_cannot_query_shared_appointment_availability(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service(['duration_minutes' => 30]);
        $appointment = app(CreateAppointmentAction::class)->execute($owner, [
            'client_name' => 'María',
            'date' => '2026-07-21',
            'start_time' => '15:00',
            'items' => [$this->item($service, $employee), $this->item($service, $other)],
        ]);

        $this->actingAs($employee)->postJson('/appointments/availability', [
            'appointment_id' => $appointment->id,
            'date' => '2026-07-21',
        ])->assertUnprocessable()->assertJsonPath(
            'errors.appointment.0',
            'Esta cita incluye servicios de otras personas. Solicita a un responsable que la reprograme.',
        );
    }

    private function available(User $user, string $date, array $items): array
    {
        return app(BuildAppointmentAvailabilityAction::class)->execute($user, $date, $items)['available_times'];
    }

    private function create(User $actor, User $assignee, Service $service, string $time)
    {
        return app(CreateAppointmentAction::class)->execute($actor, ['client_name' => 'María', 'date' => '2026-07-21', 'start_time' => $time, 'items' => [$this->item($service, $assignee)]]);
    }

    private function item(Service $service, User $assignee, int $quantity = 1, ?int $duration = null): array
    {
        return ['service_id' => $service->id, 'assigned_to' => $assignee->id, 'quantity' => $quantity, 'duration_minutes' => $duration ?? $service->duration_minutes];
    }

    private function items(Service $service, User $assignee): array
    {
        return [$this->item($service, $assignee)];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function service(array $attributes = []): Service
    {
        return Service::query()->create(['name' => 'Facial', 'duration_minutes' => 60, 'price' => '100.00', 'is_active' => true, ...$attributes]);
    }
}
