<?php

namespace Tests\Feature;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentDepositRefund;
use App\Models\AppointmentEvent;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4CAppointmentDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-24 14:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_permissions_are_idempotent_and_roles_and_routes_stop_at_phase_4c(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        foreach ([Permissions::APPOINTMENTS_MANAGE_DEPOSIT, Permissions::APPOINTMENTS_RESOLVE_DEPOSIT] as $permission) {
            $this->assertSame(1, Permission::query()->where('name', $permission)->count());
        }
        $this->assertTrue(Role::findByName('owner')->hasAllPermissions([
            Permissions::APPOINTMENTS_MANAGE_DEPOSIT,
            Permissions::APPOINTMENTS_RESOLVE_DEPOSIT,
        ]));
        $this->assertTrue(Role::findByName('administrator')->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT));
        $this->assertFalse(Role::findByName('administrator')->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT));
        $this->assertFalse(Role::findByName('employee')->hasAnyPermission([
            Permissions::APPOINTMENTS_MANAGE_DEPOSIT,
            Permissions::APPOINTMENTS_RESOLVE_DEPOSIT,
        ]));
        $this->assertTrue(Route::has('appointments.deposit'));
        $this->assertFalse(Route::has('appointments.refund'));
        $this->assertTrue(Route::has('appointments.checkout'));
        $this->assertFalse(Route::has('appointments.projection'));
        $this->assertTrue(Route::has('appointments.history'));
    }

    public function test_new_appointment_records_cash_deposit_in_the_same_transaction(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');

        $this->actingAs($owner)->post('/appointments', $this->appointmentPayload($employee) + [
            'has_deposit' => true,
            'deposit' => ['amount' => '40.25', 'payment_method' => 'cash', 'note' => 'Reserva recibida.'],
        ])->assertStatus(303);

        $appointment = Appointment::query()->firstOrFail();
        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame('40.25', $deposit->amount);
        $this->assertSame('0.00', $deposit->card_fee_rate);
        $this->assertSame('0.00', $deposit->card_fee_amount);
        $this->assertSame('40.25', $deposit->net_amount);
        $this->assertSame(AppointmentDeposit::STATUS_PENDING, $deposit->status);
        $this->assertSame($owner->id, $deposit->recorded_by);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'type' => AppointmentEvent::TYPE_DEPOSIT_RECORDED,
            'notes' => 'Reserva recibida.',
        ]);
    }

    public function test_card_fee_uses_exact_cents_and_ignores_manipulated_financial_fields(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, $this->user('employee'));

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/deposit", [
            'amount' => '10.01',
            'payment_method' => 'card',
            'card_fee_rate' => '99.00',
            'card_fee_amount' => '9.99',
            'net_amount' => '0.02',
            'total' => '1.00',
        ])->assertStatus(303);

        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame('4.00', $deposit->card_fee_rate);
        $this->assertSame('0.40', $deposit->card_fee_amount);
        $this->assertSame('9.61', $deposit->net_amount);
        $this->assertSame('10.01', $deposit->amount);
    }

    public function test_deposit_must_be_positive_not_exceed_expected_total_and_is_unique_per_appointment(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointment($owner, $this->user('employee'));

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/deposit", ['amount' => '0.00', 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->post("/appointments/{$appointment->id}/deposit", ['amount' => '100.01', 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->post("/appointments/{$appointment->id}/deposit", ['amount' => '100.00', 'payment_method' => 'cash'])
            ->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/deposit", ['amount' => '1.00', 'payment_method' => 'cash'])
            ->assertSessionHasErrors('deposit');

        $this->assertSame(1, $appointment->deposit()->count());
    }

    public function test_deposit_failure_rolls_back_new_appointment_items_and_audit(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_DEPOSIT_RECORDED
            ? throw new RuntimeException('Fallo financiero inducido')
            : null);

        try {
            app(CreateAppointmentAction::class)->execute($owner, $this->appointmentPayload($employee) + [
                'has_deposit' => true,
                'deposit' => ['amount' => '25.00', 'payment_method' => 'cash'],
            ]);
            $this->fail('La creación debía propagar el fallo del evento financiero.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo financiero inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_items', 0);
        $this->assertDatabaseCount('appointment_deposits', 0);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_full_refund_is_atomic_audited_and_idempotent_by_operation_token(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '60.00', 'card');
        $token = (string) Str::uuid();
        $payload = $this->terminalPayload('full_refund', operationToken: $token);

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", $payload)->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/cancel", $payload)->assertStatus(303);

        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame(Appointment::STATUS_CANCELED, $appointment->fresh()->status);
        $this->assertSame(AppointmentDeposit::STATUS_REFUNDED, $deposit->status);
        $this->assertSame('60.00', $deposit->refunded_amount);
        $this->assertSame('0.00', $deposit->retained_amount);
        $this->assertSame('2.40', $deposit->card_fee_amount);
        $this->assertSame('57.60', $deposit->net_amount);
        $this->assertSame(1, $deposit->refunds()->count());
        $this->assertSame(1, AppointmentEvent::query()->where('appointment_id', $appointment->id)->where('type', AppointmentEvent::TYPE_DEPOSIT_RESOLVED)->count());
        $this->assertSame(1, AppointmentEvent::query()->where('appointment_id', $appointment->id)->where('type', AppointmentEvent::TYPE_CANCELED)->count());
    }

    public function test_partial_refund_uses_strict_bounds_and_retains_the_exact_remainder(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '60.00', 'cash');

        foreach (['0.00', '60.00', '60.01'] as $invalid) {
            $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('partial_refund', $invalid, (string) Str::uuid()))
                ->assertSessionHasErrors('refund_amount');
            $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        }

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('partial_refund', '22.35', (string) Str::uuid()))
            ->assertStatus(303);
        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame(AppointmentDeposit::STATUS_PARTIALLY_REFUNDED, $deposit->status);
        $this->assertSame('22.35', $deposit->refunded_amount);
        $this->assertSame('37.65', $deposit->retained_amount);
    }

    public function test_full_retention_records_no_refund_and_no_show_resolves_atomically(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '35.00', 'cash');
        Carbon::setTestNow('2026-07-25 17:00:00 UTC');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/no-show", $this->terminalPayload('full_retention'))
            ->assertStatus(303);

        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame(Appointment::STATUS_NO_SHOW, $appointment->fresh()->status);
        $this->assertSame(AppointmentDeposit::STATUS_RETAINED, $deposit->status);
        $this->assertSame('0.00', $deposit->refunded_amount);
        $this->assertSame('35.00', $deposit->retained_amount);
        $this->assertSame(0, $deposit->refunds()->count());
    }

    public function test_pending_deposit_requires_resolution_and_admin_requires_separate_resolve_permission(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $appointment = $this->appointmentWithDeposit($owner, '30.00', 'cash');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Cancelación solicitada.'])
            ->assertSessionHasErrors('deposit_resolution');
        $this->actingAs($administrator)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('full_retention'))
            ->assertForbidden();
        $administrator->givePermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT);
        $this->actingAs($administrator)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('full_retention'))
            ->assertStatus(303);

        $this->assertSame(Appointment::STATUS_CANCELED, $appointment->fresh()->status);
        $this->assertSame($administrator->id, $appointment->deposit()->firstOrFail()->resolved_by);
    }

    public function test_employee_cannot_record_or_resolve_deposits(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, $employee);

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/deposit", ['amount' => '20.00', 'payment_method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/deposit", ['amount' => '20.00', 'payment_method' => 'cash'])
            ->assertStatus(303);
        $this->actingAs($employee)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('full_retention'))
            ->assertForbidden();

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
    }

    public function test_operation_token_cannot_be_reused_for_another_refund(): void
    {
        $owner = $this->user('owner');
        $token = (string) Str::uuid();
        $first = $this->appointmentWithDeposit($owner, '20.00', 'cash');
        $this->actingAs($owner)->post("/appointments/{$first->id}/cancel", $this->terminalPayload('full_refund', operationToken: $token))
            ->assertStatus(303);

        $second = $this->appointmentWithDeposit($owner, '30.00', 'cash');
        $this->post("/appointments/{$second->id}/cancel", $this->terminalPayload('full_refund', operationToken: $token))
            ->assertSessionHasErrors('operation_token');

        $this->assertSame(Appointment::STATUS_SCHEDULED, $second->fresh()->status);
        $this->assertSame(1, AppointmentDepositRefund::query()->where('operation_token', $token)->count());
    }

    public function test_terminal_audit_failure_rolls_back_refund_resolution_and_status(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '45.00', 'cash');
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_CANCELED
            ? throw new RuntimeException('Fallo terminal inducido')
            : null);

        try {
            app(CancelAppointmentAction::class)->execute(
                $owner,
                $appointment,
                'Debe revertirse por completo.',
                $this->terminalPayload('full_refund', operationToken: (string) Str::uuid()),
            );
            $this->fail('La resolución debía propagarse junto con el fallo terminal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo terminal inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertSame(AppointmentDeposit::STATUS_PENDING, $deposit->status);
        $this->assertSame('0.00', $deposit->refunded_amount);
        $this->assertNull($deposit->resolved_at);
        $this->assertDatabaseCount('appointment_deposit_refunds', 0);
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id, 'type' => AppointmentEvent::TYPE_DEPOSIT_RESOLVED]);
    }

    public function test_deposit_detail_exposes_capabilities_and_hides_internal_finance_from_employee(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointmentWithDeposit($owner, '60.00', 'card', $employee);

        $this->actingAs($owner)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.can_manage_deposit', true)
            ->assertJsonPath('appointment.can_resolve_deposit', true)
            ->assertJsonPath('appointment.deposit.card_fee_amount', '2.40')
            ->assertJsonPath('appointment.deposit.net_amount', '57.60')
            ->assertJsonPath('appointment.deposit.estimated_balance', '40.00');

        $this->actingAs($employee)->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.can_manage_deposit', false)
            ->assertJsonPath('appointment.can_resolve_deposit', false)
            ->assertJsonPath('appointment.deposit.amount', '60.00')
            ->assertJsonPath('appointment.deposit.status_label', 'Pendiente de aplicar')
            ->assertJsonMissingPath('appointment.deposit.refunded_amount')
            ->assertJsonMissingPath('appointment.deposit.retained_amount')
            ->assertJsonMissingPath('appointment.deposit.card_fee_rate')
            ->assertJsonMissingPath('appointment.deposit.card_fee_amount')
            ->assertJsonMissingPath('appointment.deposit.net_amount')
            ->assertJsonMissingPath('appointment.deposit.resolution_notes');
    }

    public function test_financial_records_cannot_be_deleted_and_refunds_cannot_be_edited(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '25.00', 'cash');
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload('full_refund', operationToken: (string) Str::uuid()))
            ->assertStatus(303);
        $deposit = $appointment->deposit()->firstOrFail();
        $refund = $deposit->refunds()->firstOrFail();

        foreach ([fn () => $deposit->delete(), fn () => $refund->delete(), function () use ($refund) {
            $refund->notes = 'Edición no permitida';
            $refund->save();
        }] as $operation) {
            try {
                $operation();
                $this->fail('La mutación financiera debía ser rechazada.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, AppointmentDeposit::query()->count());
        $this->assertSame(1, AppointmentDepositRefund::query()->count());
    }

    public function test_phase_4c_never_applies_or_double_counts_the_deposit(): void
    {
        $owner = $this->user('owner');
        $appointment = $this->appointmentWithDeposit($owner, '40.00', 'cash');
        $deposit = $appointment->deposit()->firstOrFail();

        $this->assertSame('0.00', $deposit->applied_amount);
        $this->assertSame('0.00', $deposit->refunded_amount);
        $this->assertSame('0.00', $deposit->retained_amount);
        $this->assertDatabaseCount('sales', 0);
        $this->assertTrue(Route::has('appointments.checkout'));
    }

    private function appointmentWithDeposit(User $owner, string $amount, string $method, ?User $employee = null): Appointment
    {
        $appointment = $this->appointment($owner, $employee ?? $this->user('employee'));
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/deposit", [
            'amount' => $amount,
            'payment_method' => $method,
            'note' => 'Adelanto de prueba.',
        ])->assertStatus(303);

        return $appointment;
    }

    private function appointment(User $actor, User $employee): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($actor, $this->appointmentPayload($employee));
    }

    private function appointmentPayload(User $employee): array
    {
        $service = Service::query()->create([
            'name' => 'Servicio '.fake()->unique()->numerify('###'),
            'duration_minutes' => 60,
            'price' => '100.00',
            'is_active' => true,
        ]);

        return [
            'client_name' => 'María López',
            'date' => '2026-07-25',
            'start_time' => '10:00',
            'items' => [[
                'service_id' => $service->id,
                'assigned_to' => $employee->id,
                'quantity' => 1,
                'duration_minutes' => 60,
            ]],
        ];
    }

    private function terminalPayload(
        string $resolution,
        ?string $refundAmount = null,
        ?string $operationToken = null,
    ): array {
        return array_filter([
            'reason' => 'Resolución terminal documentada.',
            'deposit_resolution' => $resolution,
            'refund_amount' => $refundAmount,
            'operation_token' => $operationToken,
            'resolution_notes' => 'Resolución financiera confirmada.',
        ], fn ($value) => $value !== null);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
