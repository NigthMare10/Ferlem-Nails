<?php

namespace Tests\Feature;

use App\Actions\Appointments\CheckoutAppointmentAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\RefundAppointmentDepositExcessAction;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4DAppointmentCheckoutTest extends TestCase
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

    public function test_permission_and_route_are_idempotent_and_stop_at_phase_4d(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Permission::query()->where('name', Permissions::APPOINTMENTS_CONVERT_TO_SALE)->count());
        foreach (['owner', 'administrator', 'employee'] as $role) {
            $this->assertTrue(Role::findByName($role)->hasPermissionTo(Permissions::APPOINTMENTS_CONVERT_TO_SALE));
        }
        $this->assertTrue(Route::has('appointments.checkout'));
        $this->assertTrue(Route::has('appointments.deposit.refund-excess'));
        $this->assertFalse(Route::has('appointments.projection'));
        $this->assertTrue(Route::has('appointments.history'));
    }

    public function test_preload_contains_authoritative_context_and_opening_does_not_complete(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [
            $this->line($this->service('Snapshot reservado', '123.45'), $employee, 2),
        ]);
        $this->deposit($owner, $appointment, '40.00', 'cash');

        $this->actingAs($owner)->get("/sales/new?appointment={$appointment->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Create')
                ->where('appointment.client_name', 'María López')
                ->where('appointment.items.0.name', 'Snapshot reservado')
                ->where('appointment.items.0.quantity', 2)
                ->where('appointment.items.0.performed_by.id', $employee->id)
                ->where('appointment.reserved_total', '246.90')
                ->where('appointment.deposit.amount', '40.00')
                ->where('appointment.pending_balance', '206.90'));

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_scope_permissions_active_state_and_sales_permissions_are_enforced(): void
    {
        $owner = $this->user('owner');
        $participant = $this->user('employee');
        $otherParticipant = $this->user('employee');
        $unrelated = $this->user('employee');
        $appointment = $this->appointment($owner, [
            $this->line($this->service('Primero'), $participant),
            $this->line($this->service('Compartido'), $otherParticipant),
        ]);

        $this->actingAs($participant)->get("/sales/new?appointment={$appointment->id}")->assertOk();
        $this->actingAs($unrelated)->get("/sales/new?appointment={$appointment->id}")->assertForbidden();
        $limited = User::factory()->create(['is_active' => true]);
        $limited->givePermissionTo([
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_ALL,
            Permissions::APPOINTMENTS_CONVERT_TO_SALE,
            Permissions::SALES_ACCESS,
        ]);
        $this->actingAs($limited)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment))->assertForbidden();
        $participant->update(['is_active' => false]);
        $this->actingAs($participant)->get("/sales/new?appointment={$appointment->id}")->assertRedirect(route('login'));
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
    }

    public function test_same_token_is_idempotent_and_different_token_gets_existing_sale_conflict(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$this->line($this->service('Manicura'), $employee)]);
        $token = (string) Str::uuid();
        $payload = $this->checkoutPayload($appointment, token: $token);

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/checkout", $payload)->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/checkout", $payload)->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment))->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    }

    public function test_reserved_snapshots_repeated_lines_additions_quantity_and_confirmed_removal_are_preserved(): void
    {
        $owner = $this->user('owner');
        $performer = $this->user('employee');
        $reserved = $this->service('Servicio repetido', '20.00');
        $additional = $this->service('Adicional', '15.00');
        $appointment = $this->appointment($owner, [
            $this->line($reserved, $performer),
            $this->line($reserved, $performer),
            $this->line($this->service('No realizado', '50.00'), $performer),
        ]);
        $items = $appointment->items()->orderBy('position')->get();
        $reserved->update(['name' => 'Catálogo cambiado', 'price' => '999.00', 'is_active' => false]);
        $payload = $this->checkoutPayload($appointment);
        $payload['items'] = [
            ['appointment_item_id' => $items[0]->id, 'service_id' => $reserved->id, 'quantity' => 2, 'performed_by' => $performer->id],
            ['appointment_item_id' => $items[1]->id, 'service_id' => $reserved->id, 'quantity' => 1, 'performed_by' => $performer->id],
            ['appointment_item_id' => null, 'service_id' => $additional->id, 'quantity' => 2, 'performed_by' => $performer->id],
        ];
        $payload['removed_appointment_item_ids'] = [$items[2]->id];

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $payload)->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('90.00', $sale->total);
        $this->assertSame(5, $sale->total_services);
        $this->assertSame(['Servicio repetido', 'Servicio repetido', 'Adicional'], $sale->items->pluck('service_name')->all());
        $this->assertSame(['40.00', '20.00', '30.00'], $sale->items->pluck('line_total')->all());
        $this->assertSame([null, null, $additional->id], $sale->items->pluck('service_id')->all());
    }

    public function test_reserved_line_cannot_disappear_without_explicit_confirmation(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [
            $this->line($this->service('Uno'), $employee),
            $this->line($this->service('Dos'), $employee),
        ]);
        $payload = $this->checkoutPayload($appointment);
        array_pop($payload['items']);

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/checkout", $payload)->assertSessionHasErrors('items');
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_employee_preserves_shared_performers_and_additional_lines_are_forced_to_self(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $appointment = $this->appointment($owner, [
            $this->line($this->service('Propio'), $employee),
            $this->line($this->service('Compartido'), $other),
        ]);
        $additional = $this->service('Adicional');
        $payload = $this->checkoutPayload($appointment);
        $payload['items'][] = ['appointment_item_id' => null, 'service_id' => $additional->id, 'quantity' => 1, 'performed_by' => $other->id];

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/checkout", $payload)->assertForbidden();
        $this->assertDatabaseCount('sales', 0);

        $payload['items'][2]['performed_by'] = $employee->id;
        $payload['checkout_token'] = (string) Str::uuid();
        $this->post("/appointments/{$appointment->id}/checkout", $payload)->assertStatus(303);
        $this->assertSame([$employee->id, $other->id, $employee->id], Sale::query()->firstOrFail()->items->pluck('performed_by')->all());
    }

    public function test_owner_can_assign_only_an_active_eligible_performer(): void
    {
        $owner = $this->user('owner');
        $original = $this->user('employee');
        $replacement = $this->user('employee');
        $inactive = $this->user('employee');
        $inactive->update(['is_active' => false]);
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio'), $original)]);
        $payload = $this->checkoutPayload($appointment);
        $payload['items'][0]['performed_by'] = $inactive->id;

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $payload)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);

        $payload['checkout_token'] = (string) Str::uuid();
        $payload['items'][0]['performed_by'] = $replacement->id;
        $this->post("/appointments/{$appointment->id}/checkout", $payload)->assertStatus(303);
        $this->assertSame($replacement->id, SaleItem::query()->firstOrFail()->performed_by);
    }

    public function test_deposit_and_cash_balance_create_authoritative_payments_and_full_sale_total(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio', '100.00'), $employee)]);
        $this->deposit($owner, $appointment, '25.00', 'cash');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment, method: 'cash'))->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('100.00', $sale->total);
        $this->assertSame('0.00', $sale->card_fee_amount);
        $this->assertSame(['25.00', '75.00'], $sale->payments->pluck('amount')->all());
        $this->assertSame([SalePayment::TYPE_DEPOSIT_APPLIED, SalePayment::TYPE_FINAL_PAYMENT], $sale->payments->pluck('type')->all());
        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame(AppointmentDeposit::STATUS_APPLIED, $deposit->status);
        $this->assertSame('25.00', $deposit->applied_amount);
    }

    public function test_card_fees_accumulate_only_from_their_payment_snapshots_and_allocate_exactly(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [
            $this->line($this->service('Uno', '33.33'), $employee),
            $this->line($this->service('Dos', '66.67'), $employee),
        ]);
        $this->deposit($owner, $appointment, '20.00', 'card');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment, method: 'card'))->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('100.00', $sale->total);
        $this->assertSame('4.00', $sale->card_fee_amount);
        $this->assertSame('96.00', $sale->net_amount);
        $this->assertSame(['0.80', '3.20'], $sale->payments->pluck('card_fee_amount')->all());
        $this->assertSame(['1.33', '2.67'], $sale->items->pluck('allocated_card_fee_amount')->all());
        $this->assertSame('4.00', number_format($sale->items->sum(fn ($item) => (float) $item->allocated_card_fee_amount), 2, '.', ''));
        $this->assertSame(['32.00', '64.00'], $sale->items->pluck('net_line_amount')->all());
    }

    public function test_deposit_that_covers_total_creates_no_final_payment_and_drives_compatibility_method(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio', '100.00'), $employee)]);
        $this->deposit($owner, $appointment, '100.00', 'card');

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment, method: 'cash'))->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame(Sale::PAYMENT_METHOD_CARD, $sale->payment_method);
        $this->assertSame('4.00', $sale->card_fee_amount);
        $this->assertSame('96.00', $sale->net_amount);
        $this->assertSame(1, $sale->payments()->count());
        $this->assertSame(SalePayment::TYPE_DEPOSIT_APPLIED, $sale->payments()->firstOrFail()->type);
    }

    public function test_total_below_deposit_is_blocked_without_negative_balance_or_completion(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Reservado', '100.00'), $employee)]);
        $this->deposit($owner, $appointment, '80.00', 'cash');
        $payload = $this->checkoutPayload($appointment);
        $payload['items'][0]['quantity'] = 0;
        $payload['items'] = [[
            'appointment_item_id' => null,
            'service_id' => $this->service('Menor', '50.00')->id,
            'quantity' => 1,
            'performed_by' => $employee->id,
        ]];
        $payload['removed_appointment_item_ids'] = [$appointment->items()->firstOrFail()->id];

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $payload)
            ->assertSessionHasErrors(['items' => 'El total de los servicios realizados no puede ser menor que el adelanto. Ajusta los servicios o el monto antes de completar la cita.']);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertSame(AppointmentDeposit::STATUS_PENDING, $appointment->deposit()->firstOrFail()->status);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_excess_refund_permissions_scope_and_explicit_resolve_grant_are_enforced(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $administrator = $this->user('administrator');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio'), $employee)]);
        $this->deposit($owner, $appointment, '80.00', 'cash');
        $payload = $this->excessRefundPayload('10.00');

        $this->actingAs($employee)->post("/appointments/{$appointment->id}/deposit/refund-excess", $payload)->assertForbidden();
        $this->actingAs($administrator)->post("/appointments/{$appointment->id}/deposit/refund-excess", $payload)->assertForbidden();

        $scoped = User::factory()->create(['is_active' => true]);
        $scoped->givePermissionTo([
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_RESOLVE_DEPOSIT,
        ]);
        $this->actingAs($scoped)->post("/appointments/{$appointment->id}/deposit/refund-excess", $payload)->assertForbidden();

        $administrator->givePermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT);
        $this->actingAs($administrator)->post("/appointments/{$appointment->id}/deposit/refund-excess", $payload)->assertStatus(303);
        $this->assertSame('10.00', $appointment->deposit()->firstOrFail()->refunded_amount);
    }

    public function test_exact_excess_refund_is_immutable_audited_idempotent_and_enables_checkout(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Reservado', '100.00'), $employee)]);
        $this->deposit($owner, $appointment, '80.00', 'card');
        $actual = $this->service('Trabajo real', '50.00');
        $checkout = $this->checkoutPayload($appointment);
        $checkout['items'] = [[
            'appointment_item_id' => null,
            'service_id' => $actual->id,
            'quantity' => 1,
            'performed_by' => $employee->id,
        ]];
        $checkout['removed_appointment_item_ids'] = [$appointment->items()->firstOrFail()->id];

        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $checkout)->assertSessionHasErrors('items');
        $this->get("/sales/new?appointment={$appointment->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointment.deposit.amount', '80.00')
                ->where('appointment.deposit.available_amount', '80.00')
                ->where('appointment.can_resolve_deposit', true));

        $token = (string) Str::uuid();
        $refund = $this->excessRefundPayload('30.00', $token);
        $this->post("/appointments/{$appointment->id}/deposit/refund-excess", $refund)->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/deposit/refund-excess", $refund)->assertStatus(303);
        $this->post("/appointments/{$appointment->id}/deposit/refund-excess", $this->excessRefundPayload('20.00', $token))->assertSessionHasErrors('operation_token');

        $deposit = $appointment->deposit()->firstOrFail();
        $refundRow = $deposit->refunds()->firstOrFail();
        $this->assertSame('80.00', $deposit->amount);
        $this->assertSame('4.00', $deposit->card_fee_rate);
        $this->assertSame('3.20', $deposit->card_fee_amount);
        $this->assertSame('76.80', $deposit->net_amount);
        $this->assertSame('30.00', $deposit->refunded_amount);
        $this->assertSame('50.00', $deposit->availableAmount());
        $this->assertSame(AppointmentDeposit::STATUS_PENDING, $deposit->status);
        $this->assertSame('excess', $refundRow->purpose);
        $this->assertSame(1, $deposit->refunds()->count());
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'type' => AppointmentEvent::TYPE_DEPOSIT_EXCESS_REFUNDED]);
        $this->get("/sales/new?appointment={$appointment->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointment.deposit.available_amount', '50.00')
                ->where('appointment.pending_balance', '50.00')
                ->missing('appointment.deposit.card_fee_amount'));
        $this->getJson("/appointments/{$appointment->id}")
            ->assertJsonPath('appointment.deposit.available_amount', '50.00')
            ->assertJsonPath('appointment.events.0.type', AppointmentEvent::TYPE_DEPOSIT_EXCESS_REFUNDED);

        foreach ([function () use ($refundRow) {
            $refundRow->amount = '1.00';
            $refundRow->save();
        }, fn () => $refundRow->delete()] as $operation) {
            try {
                $operation();
                $this->fail('La devolución debía permanecer inmutable.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $checkout['checkout_token'] = (string) Str::uuid();
        $this->post("/appointments/{$appointment->id}/checkout", $checkout)->assertStatus(303);
        $sale = Sale::query()->firstOrFail();
        $this->assertSame('50.00', $sale->total);
        $this->assertSame('50.00', $sale->payments()->firstOrFail()->amount);
        $this->assertSame('50.00', $deposit->fresh()->applied_amount);
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);

        $second = $this->appointment($owner, [$this->line($this->service('Otra cita'), $employee)]);
        $this->deposit($owner, $second, '40.00', 'cash');
        $this->post("/appointments/{$second->id}/deposit/refund-excess", $this->excessRefundPayload('30.00', $token))
            ->assertSessionHasErrors('operation_token');
        $this->assertSame('0.00', $second->deposit()->firstOrFail()->refunded_amount);
    }

    public function test_terminal_resolutions_accumulate_prior_excess_refunds_against_available_amount(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');

        $cases = [
            ['full_refund', null, '60.00', '0.00'],
            ['full_retention', null, '10.00', '50.00'],
            ['partial_refund', '20.00', '30.00', '30.00'],
        ];
        foreach ($cases as $index => [$resolution, $terminalRefund, $expectedRefunded, $expectedRetained]) {
            $appointment = $this->appointment($owner, [$this->line($this->service("Terminal {$index}"), $employee)]);
            $this->deposit($owner, $appointment, '60.00', 'cash');
            $this->post("/appointments/{$appointment->id}/deposit/refund-excess", $this->excessRefundPayload('10.00'))->assertStatus(303);
            $this->post("/appointments/{$appointment->id}/cancel", $this->terminalPayload($resolution, $terminalRefund))->assertStatus(303);
            $deposit = $appointment->deposit()->firstOrFail();
            $this->assertSame($expectedRefunded, $deposit->refunded_amount);
            $this->assertSame($expectedRetained, $deposit->retained_amount);
        }
    }

    public function test_excess_refund_audit_failure_rolls_back_refund_and_available_amount(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio'), $employee)]);
        $this->deposit($owner, $appointment, '50.00', 'cash');
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_DEPOSIT_EXCESS_REFUNDED
            ? throw new RuntimeException('Fallo de devolución inducido')
            : null);

        try {
            app(RefundAppointmentDepositExcessAction::class)->execute($owner, $appointment, $this->excessRefundPayload('10.00'));
            $this->fail('La devolución debía revertirse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de devolución inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $deposit = $appointment->deposit()->firstOrFail();
        $this->assertSame('0.00', $deposit->refunded_amount);
        $this->assertSame('50.00', $deposit->availableAmount());
        $this->assertDatabaseCount('appointment_deposit_refunds', 0);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
    }

    public function test_sale_financial_rows_cannot_be_updated_or_physically_deleted(): void
    {
        $employee = $this->user('employee');
        $appointment = $this->appointment($employee, [$this->line($this->service('Servicio'), $employee)]);
        $this->actingAs($employee)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment))->assertStatus(303);
        $sale = Sale::query()->firstOrFail();
        $item = $sale->items()->firstOrFail();
        $payment = $sale->payments()->firstOrFail();

        foreach ([
            fn () => $sale->delete(),
            function () use ($item) {
                $item->quantity = 2;
                $item->save();
            },
            fn () => $item->delete(),
            function () use ($payment) {
                $payment->amount = '1.00';
                $payment->save();
            },
            fn () => $payment->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('La fila financiera debía permanecer inmutable.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('sale_payments', 1);
    }

    public function test_failure_rolls_back_sale_payments_deposit_completion_and_event(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio'), $employee)]);
        $this->deposit($owner, $appointment, '20.00', 'cash');
        $dispatcher = AppointmentEvent::getEventDispatcher();
        AppointmentEvent::setEventDispatcher(clone $dispatcher);
        AppointmentEvent::created(fn (AppointmentEvent $event) => $event->type === AppointmentEvent::TYPE_COMPLETED
            ? throw new RuntimeException('Fallo final inducido')
            : null);

        try {
            app(CheckoutAppointmentAction::class)->execute($owner, $appointment, $this->checkoutPayload($appointment));
            $this->fail('El checkout debía revertirse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo final inducido', $exception->getMessage());
        } finally {
            AppointmentEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->fresh()->status);
        $this->assertSame(AppointmentDeposit::STATUS_PENDING, $appointment->deposit()->firstOrFail()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id, 'type' => AppointmentEvent::TYPE_COMPLETED]);
    }

    public function test_completed_detail_and_receipt_show_safe_conversion_information(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, [$this->line($this->service('Servicio', '100.00'), $employee)]);
        $this->deposit($owner, $appointment, '20.00', 'card');
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/checkout", $this->checkoutPayload($appointment, method: 'cash'));
        $sale = Sale::query()->firstOrFail();

        $this->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'completed')
            ->assertJsonPath('appointment.can_checkout', false)
            ->assertJsonPath('appointment.linked_sale.sale_number', $sale->sale_number)
            ->assertJsonPath('appointment.events.0.type', 'completed');
        $this->get(route('sales.receipt', $sale))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sale.client.name', 'María López')
                ->where('sale.total', '100.00')
                ->where('sale.items.0.performed_by.name', $employee->name)
                ->where('sale.payments.0.type_label', 'Adelanto aplicado')
                ->where('sale.payments.1.type_label', 'Saldo final pagado')
                ->missing('sale.card_fee_amount')
                ->missing('sale.net_amount')
                ->missing('sale.items.0.allocated_card_fee_amount'));
    }

    private function checkoutPayload(Appointment $appointment, ?string $token = null, string $method = 'cash'): array
    {
        return [
            'checkout_token' => $token ?? (string) Str::uuid(),
            'payment_method' => $method,
            'items' => $appointment->items()->orderBy('position')->get()->map(fn ($item) => [
                'appointment_item_id' => $item->id,
                'service_id' => $item->service_id,
                'quantity' => $item->quantity,
                'performed_by' => $item->assigned_to,
            ])->all(),
            'removed_appointment_item_ids' => [],
        ];
    }

    private function excessRefundPayload(string $amount, ?string $token = null): array
    {
        return [
            'amount' => $amount,
            'operation_token' => $token ?? (string) Str::uuid(),
            'note' => 'Ajuste exacto antes del cobro.',
        ];
    }

    private function terminalPayload(string $resolution, ?string $refundAmount = null): array
    {
        return array_filter([
            'reason' => 'Resolución terminal posterior al ajuste.',
            'deposit_resolution' => $resolution,
            'refund_amount' => $refundAmount,
            'operation_token' => in_array($resolution, ['full_refund', 'partial_refund'], true) ? (string) Str::uuid() : null,
            'resolution_notes' => 'Saldo disponible resuelto.',
        ], fn ($value) => $value !== null);
    }

    private function appointment(User $actor, array $lines): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($actor, [
            'client_name' => 'María López',
            'client_phone' => '9999-9999',
            'date' => '2026-07-25',
            'start_time' => '10:00',
            'items' => $lines,
        ]);
    }

    private function line(Service $service, User $performer, int $quantity = 1): array
    {
        return ['service_id' => $service->id, 'assigned_to' => $performer->id, 'quantity' => $quantity, 'duration_minutes' => 30];
    }

    private function deposit(User $owner, Appointment $appointment, string $amount, string $method): void
    {
        $this->actingAs($owner)->post("/appointments/{$appointment->id}/deposit", ['amount' => $amount, 'payment_method' => $method])->assertStatus(303);
    }

    private function service(string $name, string $price = '100.00'): Service
    {
        return Service::query()->create([
            'name' => $name,
            'description' => "Descripción de {$name}",
            'duration_minutes' => 30,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
