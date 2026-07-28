<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\ProcessExpiredAppointmentsAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\InternalNotification;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentCheckoutExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-21 07:00:00 America/Tegucigalpa');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_checkout_is_available_during_service_after_end_and_at_grace_deadline(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$employee]);

        foreach (['2026-07-21 08:30:00 America/Tegucigalpa', '2026-07-21 09:01:00 America/Tegucigalpa', '2026-07-21 09:30:00 America/Tegucigalpa'] as $now) {
            Carbon::setTestNow($now);
            $this->actingAs($employee)->get("/sales/new?appointment={$appointment->id}")->assertOk();
        }
    }

    public function test_expired_checkout_is_rejected_marked_no_show_and_removed_from_agenda(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$employee]);
        Carbon::setTestNow('2026-07-21 09:31:00 America/Tegucigalpa');

        $this->actingAs($owner)->get("/sales/new?appointment={$appointment->id}")
            ->assertSessionHasErrors('appointment');
        $this->assertSame(Appointment::STATUS_NO_SHOW, $appointment->fresh()->status);
        $this->assertSame('Marcada automáticamente al vencer el tiempo disponible para cobrar.', $appointment->fresh()->no_show_reason);
        $this->actingAs($owner)->get('/appointments?view=day&date=2026-07-21&month=2026-07')
            ->assertInertia(fn ($page) => $page->has('appointments', 0));
        $this->actingAs($owner)->get('/appointments/history')->assertOk();
        $this->actingAs($owner)->getJson("/appointments/{$appointment->id}")
            ->assertOk()->assertJsonPath('appointment.events.0.performed_by.name', 'Sistema');
    }

    public function test_agenda_get_filters_expired_appointments_without_mutating_them(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$employee]);
        Carbon::setTestNow('2026-07-21 09:31:00 America/Tegucigalpa');

        $this->actingAs($owner)->get('/appointments?view=day&date=2026-07-21&month=2026-07')
            ->assertOk()->assertInertia(fn ($page) => $page->has('appointments', 0));
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertSame(1, AppointmentEvent::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_expiration_notifies_once_uses_total_shared_end_and_never_expires_completed_appointments(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee');
        $second = $this->user('employee');
        $third = $this->user('employee');
        $shared = $this->appointment($owner, [$first, $second]);
        $completed = $this->appointment($owner, [$third]);
        $completed->forceFill(['status' => Appointment::STATUS_COMPLETED])->save();
        $action = app(ProcessExpiredAppointmentsAction::class);

        Carbon::setTestNow('2026-07-21 10:01:00 America/Tegucigalpa');
        $action->execute();
        $this->assertSame(Appointment::STATUS_SCHEDULED, $shared->fresh()->status);
        $this->assertSame(Appointment::STATUS_COMPLETED, $completed->fresh()->status);
        $pendingCount = InternalNotification::query()->where('dedupe_key', "appointment:{$shared->id}:checkout-grace")->count();
        $this->assertGreaterThanOrEqual(3, $pendingCount);

        Carbon::setTestNow('2026-07-21 10:31:00 America/Tegucigalpa');
        $action->execute();
        $action->execute();
        $this->assertSame(Appointment::STATUS_NO_SHOW, $shared->fresh()->status);
        $this->assertSame(1, AppointmentEvent::query()->where('appointment_id', $shared->id)->where('type', AppointmentEvent::TYPE_NO_SHOW)->count());
        $expiredCount = InternalNotification::query()->where('dedupe_key', "appointment:{$shared->id}:expired")->count();
        $this->assertSame($pendingCount, $expiredCount);
    }

    public function test_agenda_checkout_ui_always_finishes_loading_and_surfaces_errors(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString('checkoutLoading.value = false;', $page);
        $this->assertStringContainsString('} finally {', $page);
        $this->assertStringContainsString('checkoutError.value', $page);
        $this->assertStringContainsString("operational_status === 'pending_checkout'", $page);
        $this->assertStringContainsString('Quedan {{ appointment.checkout_remaining_minutes }} minutos para cobrar.', $page);
    }

    private function appointment(User $actor, array $assignees): Appointment
    {
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 60, 'price' => '100.00', 'is_active' => true]);

        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => 'María López', 'date' => '2026-07-21', 'start_time' => '08:00',
            'items' => collect($assignees)->map(fn (User $assignee) => [
                'service_id' => $service->id, 'assigned_to' => $assignee->id, 'quantity' => 1, 'duration_minutes' => 60,
            ])->all(),
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
