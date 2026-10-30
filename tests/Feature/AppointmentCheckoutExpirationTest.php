<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\InternalNotification;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
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

    public function test_scheduled_appointment_can_be_checked_out_during_hours_after_and_days_after_service(): void
    {
        foreach ([
            '2026-07-21 08:30:00 America/Tegucigalpa',
            '2026-07-21 15:00:00 America/Tegucigalpa',
            '2026-07-24 10:00:00 America/Tegucigalpa',
        ] as $now) {
            Carbon::setTestNow('2026-07-21 07:00:00 America/Tegucigalpa');
            $employee = $this->user('employee');
            $appointment = $this->appointment($employee, $employee);
            Carbon::setTestNow($now);

            $this->actingAs($employee)->get("/sales/new?appointment={$appointment->id}")->assertOk();
            $this->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment))->assertStatus(303);

            $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
            $this->assertSame($appointment->id, Sale::query()->latest('id')->value('appointment_id'));
        }
    }

    public function test_past_scheduled_appointment_never_expires_and_remains_visible_in_agenda(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, $employee);
        Carbon::setTestNow('2026-07-28 10:00:00 America/Tegucigalpa');

        $this->actingAs($owner)->get('/appointments?view=day&date=2026-07-21&month=2026-07')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.id', $appointment->id)
                ->where('appointments.0.status', Appointment::STATUS_SCHEDULED)
                ->where('appointments.0.status_label', 'Pendiente de cobro')
                ->where('appointments.0.can_checkout', true));

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertNull($appointment->no_show_at);
        $this->assertSame(0, InternalNotification::query()->where('dedupe_key', 'like', '%checkout-grace%')->orWhere('dedupe_key', 'like', '%expired%')->count());
        $this->assertArrayNotHasKey('studio:process-expired-appointments', Artisan::all());
        $this->actingAs($owner)->get('/appointments/history')->assertOk();
    }

    public function test_past_scheduled_appointment_can_still_be_marked_no_show_manually(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, $employee);
        Carbon::setTestNow('2026-07-24 10:00:00 America/Tegucigalpa');

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/no-show", [
            'reason' => 'La clienta no se presentó.',
        ])->assertStatus(303);

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_NO_SHOW, $appointment->status);
        $this->assertSame($employee->id, $appointment->no_show_by);
        $this->assertSame('La clienta no se presentó.', $appointment->no_show_reason);
    }

    public function test_agenda_uses_pending_checkout_without_deadline_or_countdown_copy(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString('Pendiente de cobro', $page);
        $this->assertStringNotContainsString('checkout_remaining_minutes', $page);
        $this->assertStringNotContainsString('checkout_deadline', $page);
        $this->assertStringNotContainsString('Quedan {{', $page);
        $this->assertStringNotContainsString('Cobrar antes de', $page);
        $this->assertStringContainsString('Atender y cobrar', $page);
        $this->assertStringContainsString('No llegó', $page);
    }

    private function appointment(User $actor, User $assignee): Appointment
    {
        $service = Service::query()->create(['name' => 'Manicura', 'description' => 'Servicio reservado', 'duration_minutes' => 60, 'price' => '100.00', 'is_active' => true]);

        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => 'María López',
            'date' => '2026-07-21',
            'start_time' => '08:00',
            'items' => [[
                'service_id' => $service->id,
                'assigned_to' => $assignee->id,
                'quantity' => 1,
                'duration_minutes' => 60,
            ]],
        ]);
    }

    private function checkoutPayload(Appointment $appointment): array
    {
        return [
            'checkout_token' => (string) Str::uuid(),
            'appointment_id' => $appointment->id,
            'payment_method' => 'cash',
            'items' => $appointment->items()->orderBy('position')->get()->map(fn ($item) => [
                'appointment_item_id' => $item->id,
                'service_id' => $item->service_id,
                'quantity' => $item->quantity,
                'performed_by' => $item->assigned_to,
            ])->all(),
            'removed_appointment_item_ids' => [],
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
