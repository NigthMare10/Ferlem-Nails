<?php

namespace Tests\Feature;

use App\Actions\Appointments\BuildAppointmentCalendarAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(DatabaseSeeder::class); Carbon::setTestNow('2026-07-20 14:00:00'); }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_monthly_calendar_scopes_segments_counts_distinct_appointments_and_keeps_payload_compact(): void
    {
        $owner = $this->user('owner'); $first = $this->user('employee'); $second = $this->user('employee');
        $facial = $this->service('Facial'); $pedi = $this->service('Pedispa');
        app(CreateAppointmentAction::class)->execute($owner, ['client_name' => 'Lucía', 'date' => '2026-07-21', 'start_time' => '15:00', 'items' => [$this->item($facial, $first), $this->item($pedi, $second)]]);
        $calendar = app(BuildAppointmentCalendarAction::class);
        $all = $calendar->execute($owner, '2026-07');
        $own = $calendar->execute($first, '2026-07');

        $this->assertSame(1, $all[0]['appointments_count']); $this->assertSame(2, $all[0]['services_count']);
        $this->assertSame(1, $own[0]['services_count']); $this->assertSame('Facial', $own[0]['previews'][0]['service_name']);
        $this->assertNull($own[0]['previews'][0]['assigned_name']); $this->assertTrue($own[0]['previews'][0]['is_shared']);
        $this->assertArrayNotHasKey('notes', $own[0]['previews'][0]); $this->assertArrayNotHasKey('events', $own[0]['previews'][0]);
    }

    public function test_view_all_employee_filter_and_month_query_validation_are_enforced(): void
    {
        $owner = $this->user('owner'); $employee = $this->user('employee'); $other = $this->user('employee'); $service = $this->service('Facial');
        $this->create($owner, $employee, $service, '15:00'); $this->create($owner, $other, $service, '16:00');
        $this->actingAs($owner)->get('/appointments?view=month&month=2026-07&employee_id='.$employee->id)
            ->assertInertia(fn ($page) => $page->where('view', 'month')->where('employee_id', $employee->id)->where('calendar_days.0.services_count', 1));
        $this->actingAs($employee)->get('/appointments?view=month&month=2026-07&employee_id='.$other->id)->assertSessionHasErrors('employee_id');
    }

    public function test_monthly_calendar_counts_and_previews_only_scheduled_appointments(): void
    {
        $owner = $this->user('owner'); $employee = $this->user('employee');
        $scheduled = $this->create($owner, $employee, $this->service('Programado'), '09:00');
        $canceled = $this->create($owner, $employee, $this->service('Cancelado'), '10:00');
        $noShow = $this->create($owner, $employee, $this->service('No llegó'), '11:00');
        $completed = $this->create($owner, $employee, $this->service('Completado'), '12:00');
        foreach ([
            [$canceled, Appointment::STATUS_CANCELED],
            [$noShow, Appointment::STATUS_NO_SHOW],
            [$completed, Appointment::STATUS_COMPLETED],
        ] as [$appointment, $status]) {
            $appointment->status = $status;
            $appointment->save();
        }

        $calendar = app(BuildAppointmentCalendarAction::class);
        foreach ([$calendar->execute($owner, '2026-07'), $calendar->execute($employee, '2026-07')] as $days) {
            $this->assertCount(1, $days);
            $this->assertSame(1, $days[0]['appointments_count']);
            $this->assertSame(1, $days[0]['services_count']);
            $this->assertTrue($days[0]['has_appointments']);
            $this->assertCount(1, $days[0]['previews']);
            $this->assertSame($scheduled->id, $days[0]['previews'][0]['appointment_id']);
            $this->assertSame('Programado', $days[0]['previews'][0]['service_name']);
        }

        $scheduled->status = Appointment::STATUS_CANCELED;
        $scheduled->save();
        $this->assertSame([], $calendar->execute($owner, '2026-07'));
        $this->assertSame([], $calendar->execute($employee, '2026-07'));
    }

    public function test_appointments_opens_month_by_default_and_only_returns_the_active_view_payload(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->get('/appointments')
            ->assertInertia(fn ($page) => $page
                ->where('view', 'month')
                ->has('appointments', 0));

        $this->get('/appointments?view=day&month=2026-07&date=2026-07-21')
            ->assertInertia(fn ($page) => $page
                ->where('view', 'day')
                ->where('month', '2026-07')
                ->where('date', '2026-07-21')
                ->has('calendar_days', 0));
    }

    public function test_calendar_ui_uses_implicit_navigation_and_accessible_reduced_motion(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($component);
        $this->assertStringNotContainsString('Vista Mes', $component);
        $this->assertStringNotContainsString('Vista Día', $component);
        $this->assertStringContainsString('Volver al calendario', $component);
        $this->assertStringContainsString('<Transition name="agenda-view" mode="out-in">', $component);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $component);
        $this->assertStringContainsString("view: 'day', date, month: props.month", $component);
        $this->assertStringContainsString("view: 'month', month, date: props.date", $component);
        $this->assertStringNotContainsString('@wheel', $component);
    }

    private function create(User $actor, User $assignee, Service $service, string $time): Appointment { return app(CreateAppointmentAction::class)->execute($actor, ['client_name' => 'Lucía', 'date' => '2026-07-21', 'start_time' => $time, 'items' => [$this->item($service, $assignee)]]); }
    private function item(Service $service, User $user): array { return ['service_id' => $service->id, 'assigned_to' => $user->id, 'quantity' => 1, 'duration_minutes' => 60]; }
    private function user(string $role): User { $user = User::factory()->create(['is_active' => true]); $user->assignRole($role); return $user; }
    private function service(string $name): Service { return Service::query()->create(['name' => $name, 'duration_minutes' => 60, 'price' => '100.00', 'is_active' => true]); }
}
