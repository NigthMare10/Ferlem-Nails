<?php

namespace Tests\Feature;

use App\Actions\Appointments\BuildAppointmentAvailabilityAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentBusinessHoursTest extends TestCase
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

    public function test_owner_and_administrator_can_manage_hours_but_employee_receives_forbidden(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');

        $this->actingAs($owner)->get('/configuration/business-hours')->assertOk();
        $this->actingAs($administrator)->get('/configuration/business-hours')->assertOk();
        $this->actingAs($employee)->get('/configuration/business-hours')->assertForbidden();
        $this->actingAs($employee)->put('/configuration/business-hours', ['hours' => $this->hours()])->assertForbidden();
    }

    public function test_hours_are_independent_and_validate_opening_before_closing(): void
    {
        $owner = $this->user('owner');
        $hours = $this->hours();
        $hours[0]['opens_at'] = '08:00';
        $hours[0]['closes_at'] = '19:00';
        $hours[6]['is_open'] = false;
        $hours[6]['opens_at'] = null;
        $hours[6]['closes_at'] = null;

        $this->actingAs($owner)->put('/configuration/business-hours', ['hours' => $hours])->assertSessionHasNoErrors();
        $this->assertSame('19:00', BusinessHour::query()->where('weekday', 1)->value('closes_at'));
        $this->assertFalse((bool) BusinessHour::query()->where('weekday', 7)->value('is_open'));

        $hours[0]['is_open'] = true;
        $hours[0]['opens_at'] = '19:00';
        $hours[0]['closes_at'] = '08:00';
        $this->actingAs($owner)->put('/configuration/business-hours', ['hours' => $hours])
            ->assertSessionHasErrors('hours.0.closes_at');
    }

    public function test_closed_day_returns_no_slots_and_service_must_finish_at_or_before_close(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service(60);
        BusinessHour::query()->where('weekday', 2)->update(['is_open' => false, 'opens_at' => null, 'closes_at' => null]);

        $closed = app(BuildAppointmentAvailabilityAction::class)->execute($owner, '2026-07-21', [$this->item($service, $employee)]);
        $this->assertFalse($closed['has_availability']);
        $this->assertSame([], $closed['available_times']);

        BusinessHour::query()->where('weekday', 2)->update(['is_open' => true, 'opens_at' => '08:00:00', 'closes_at' => '19:00:00']);
        $sixtyMinutes = app(BuildAppointmentAvailabilityAction::class)->execute($owner, '2026-07-21', [$this->item($service, $employee)]);
        $this->assertContains('18:00', $sixtyMinutes['available_times']);
        $this->assertNotContains('18:15', $sixtyMinutes['available_times']);

        $thirtyMinutes = $this->service(30);
        $thirtyMinuteSlots = app(BuildAppointmentAvailabilityAction::class)->execute($owner, '2026-07-21', [$this->item($thirtyMinutes, $employee)]);
        $this->assertContains('18:30', $thirtyMinuteSlots['available_times']);
        $this->assertNotContains('18:45', $thirtyMinuteSlots['available_times']);
    }

    public function test_historical_appointment_is_preserved_and_marked_when_hours_change(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service(60);
        $appointment = app(CreateAppointmentAction::class)->execute($owner, [
            'client_name' => 'María', 'date' => '2026-07-21', 'start_time' => '17:00', 'items' => [$this->item($service, $employee)],
        ]);

        BusinessHour::query()->where('weekday', 2)->update(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);
        $appointment->refresh();
        $this->assertSame('17:00', $appointment->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'));
        $this->actingAs($owner)->getJson("/appointments/{$appointment->id}")
            ->assertOk()->assertJsonPath('appointment.outside_business_hours', true);
    }

    public function test_reprogramming_uses_the_configured_hours(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service(60);
        $appointment = app(CreateAppointmentAction::class)->execute($owner, [
            'client_name' => 'María', 'date' => '2026-07-21', 'start_time' => '15:00', 'items' => [$this->item($service, $employee)],
        ]);
        BusinessHour::query()->where('weekday', 2)->update(['opens_at' => '08:00:00', 'closes_at' => '17:00:00']);

        $this->expectException(ValidationException::class);
        app(RescheduleAppointmentAction::class)->execute($owner, $appointment, [
            'date' => '2026-07-21', 'start_time' => '16:15', 'assignments' => [],
        ]);
    }

    private function hours(): array
    {
        return BusinessHour::query()->orderBy('weekday')->get()->map(fn (BusinessHour $hour) => [
            'weekday' => $hour->weekday,
            'is_open' => $hour->is_open,
            'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
            'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
        ])->all();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function service(int $duration): Service
    {
        return Service::query()->create(['name' => "Servicio $duration", 'duration_minutes' => $duration, 'price' => '100.00', 'is_active' => true]);
    }

    private function item(Service $service, User $employee): array
    {
        return ['service_id' => $service->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => $service->duration_minutes];
    }
}
