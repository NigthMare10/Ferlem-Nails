<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentDepositRefund;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4EAppointmentProjectionTest extends TestCase
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

    public function test_projection_permission_is_idempotent_owner_only_and_never_leaks_to_actual_only_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, Permission::query()->where('name', Permissions::APPOINTMENTS_VIEW_PROJECTION)->count());
        $this->assertTrue(Role::findByName('owner')->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_PROJECTION));
        $this->assertFalse(Role::findByName('administrator')->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_PROJECTION));
        $this->assertFalse(Role::findByName('employee')->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_PROJECTION));

        $employee = $this->user('employee');
        $this->actingAs($employee)->get('/earnings')->assertForbidden();

        $administrator = $this->user('administrator');
        $administrator->givePermissionTo(Permissions::REPORTS_SALES_VIEW);
        $sale = $this->sale($administrator, '2026-07-24 09:00:00', '10.00', '0.00', ['cash']);
        $this->saleItem($sale, $administrator, '10.00', 1, '0.00');
        $this->actingAs($administrator)->get('/earnings')->assertInertia(fn (Assert $page) => $page
            ->where('filters.mode', 'actual')
            ->where('canViewProjection', false)
            ->has('actual')
            ->missing('projection')
            ->missing('other_income')
            ->missing('outflows')
            ->has('employees', 1)
            ->missing('employees.0.projected_income'));
        $this->from('/earnings')->get('/earnings?mode=projection')->assertSessionHasErrors('mode');
        $this->from('/earnings')->get('/earnings?mode=both')->assertSessionHasErrors('mode');

        $owner = $this->user('owner');
        $this->actingAs($owner)->from('/earnings')->get('/earnings?mode=history')->assertSessionHasErrors('mode');
    }

    public function test_scheduled_projection_excludes_terminal_statuses_and_deposit_reduces_balance_not_gross(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee', 'Ana');
        $second = $this->user('employee', 'Bea');
        $scheduled = $this->appointment($owner, '2026-07-25 10:00:00', [[$first, '60.00', 2], [$second, '40.00', 1]]);
        $this->deposit($scheduled, $owner, '25.00');
        foreach ([Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW] as $index => $status) {
            $this->appointment($owner, '2026-07-25 '.(12 + $index).':00:00', [[$first, '500.00', 1]], $status);
        }

        $this->actingAs($owner)->get('/earnings?mode=projection&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->missing('actual')
                ->where('projection.appointments_count', 1)
                ->where('projection.services_count', 3)
                ->where('projection.projected_gross', '100.00')
                ->where('projection.deposits_received', '25.00')
                ->where('projection.pending_balance', '75.00')
                ->has('employees', 2)
                ->where('employees.0.projected_income', '60.00')
                ->where('employees.0.projected_pending_balance', '45.00')
                ->where('employees.1.projected_income', '40.00')
                ->where('employees.1.projected_pending_balance', '30.00'));
    }

    public function test_employee_projection_uses_assigned_segments_and_shared_appointment_is_never_duplicated(): void
    {
        $owner = $this->user('owner');
        $ana = $this->user('employee', 'Ana');
        $bea = $this->user('employee', 'Bea');
        $appointment = $this->appointment($owner, '2026-07-25 10:00:00', [[$ana, '33.33', 1], [$bea, '66.67', 2]]);
        $this->deposit($appointment, $owner, '10.00');

        $this->actingAs($owner)->get("/earnings?mode=both&period=today&date=2026-07-25&employee_id={$ana->id}&payment_method=card")
            ->assertInertia(fn (Assert $page) => $page
                ->where('actual.gross_revenue', '0.00')
                ->where('projection.appointments_count', 1)
                ->where('projection.services_count', 1)
                ->where('projection.projected_gross', '33.33')
                ->where('projection.deposits_received', '3.33')
                ->where('projection.pending_balance', '30.00')
                ->has('employees', 1)
                ->where('employees.0.id', $ana->id));

        $this->get("/earnings?mode=projection&period=today&date=2026-07-25&employee_id={$bea->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('projection.appointments_count', 1)
                ->where('projection.projected_gross', '66.67')
                ->where('projection.deposits_received', '6.67'));

        $this->get('/earnings?mode=projection&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projection.appointments_count', 1)
                ->where('projection.projected_gross', '100.00')
                ->where('projection.deposits_received', '10.00')
                ->where('projection.pending_balance', '90.00'));
    }

    public function test_actual_mode_includes_real_adjustments_without_projection_payload(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment(
            $owner,
            '2026-07-20 10:00:00',
            [[$employee, '100.00', 1]],
            Appointment::STATUS_NO_SHOW,
        );
        $deposit = $this->deposit($appointment, $owner, '60.00', AppointmentDeposit::STATUS_PARTIALLY_REFUNDED, [
            'refunded_amount' => '15.00',
            'retained_amount' => '45.00',
            'resolved_at' => $this->utc('2026-07-25 09:00:00'),
        ]);
        $this->refund($deposit, $owner, '15.00', '2026-07-25 09:05:00');

        $this->actingAs($owner)->get('/earnings?mode=actual&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->has('actual')
                ->missing('projection')
                ->where('other_income.retained_deposits_count', 1)
                ->where('other_income.retained_deposits', '45.00')
                ->where('outflows.refunds_count', 1)
                ->where('outflows.refunded_deposits', '15.00'));
    }

    public function test_retained_deposits_and_refunds_are_separate_and_employee_scope_counts_each_once(): void
    {
        $owner = $this->user('owner');
        $ana = $this->user('employee', 'Ana');
        $bea = $this->user('employee', 'Bea');
        $appointment = $this->appointment($owner, '2026-07-20 10:00:00', [[$ana, '50.00', 1], [$bea, '50.00', 1]], Appointment::STATUS_CANCELED);
        $deposit = $this->deposit($appointment, $owner, '50.00', AppointmentDeposit::STATUS_PARTIALLY_REFUNDED, [
            'refunded_amount' => '20.00',
            'retained_amount' => '30.00',
            'resolved_at' => $this->utc('2026-07-25 09:00:00'),
        ]);
        $this->refund($deposit, $owner, '20.00', '2026-07-25 09:05:00');

        $this->actingAs($owner)->get("/earnings?mode=projection&period=today&date=2026-07-25&employee_id={$ana->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('projection.projected_gross', '0.00')
                ->where('other_income.retained_deposits_count', 1)
                ->where('other_income.retained_deposits', '30.00')
                ->where('outflows.refunds_count', 1)
                ->where('outflows.refunded_deposits', '20.00'));
    }

    public function test_actual_results_and_filter_are_attributed_to_performer_not_cashier(): void
    {
        $owner = $this->user('owner', 'Caja');
        $performer = $this->user('employee', 'Ana');
        $other = $this->user('employee', 'Bea');
        $sale = $this->sale($owner, '2026-07-25 11:00:00', '100.00', '4.00', ['cash', 'card']);
        $this->saleItem($sale, $performer, '60.00', 2, '2.40');
        $this->saleItem($sale, $other, '40.00', 1, '1.60');

        $this->actingAs($owner)->get("/earnings?mode=actual&period=today&date=2026-07-25&employee_id={$performer->id}&payment_method=card")
            ->assertInertia(fn (Assert $page) => $page
                ->missing('projection')
                ->where('actual.gross_revenue', '60.00')
                ->where('actual.pos_fee', '2.40')
                ->where('actual.net_income', '57.60')
                ->where('actual.completed_sales_count', 1)
                ->where('actual.performed_services_count', 2)
                ->has('employees', 1)
                ->where('employees.0.id', $performer->id)
                ->missing('employees.0.projected_income'));

        $this->get("/earnings?mode=actual&period=today&date=2026-07-25&employee_id={$owner->id}")
            ->assertInertia(fn (Assert $page) => $page->where('actual.gross_revenue', '0.00'));
        $this->get('/earnings?mode=actual&period=today&date=2026-07-25&payment_method=cash')
            ->assertInertia(fn (Assert $page) => $page
                ->where('actual.gross_revenue', '100.00')
                ->where('actual.completed_sales_count', 1));
    }

    public function test_projection_uses_honduras_midnight_boundaries(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $this->appointment($owner, '2026-07-24 23:59:59', [[$employee, '10.00', 1]]);
        $this->appointment($owner, '2026-07-25 00:00:00', [[$employee, '20.00', 1]]);
        $this->appointment($owner, '2026-07-25 23:59:59', [[$employee, '30.00', 1]]);
        $this->appointment($owner, '2026-07-26 00:00:00', [[$employee, '40.00', 1]]);

        $this->actingAs($owner)->get('/earnings?mode=projection&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.timezone', 'America/Tegucigalpa')
                ->where('projection.appointments_count', 2)
                ->where('projection.projected_gross', '50.00'));
    }

    public function test_checkout_removes_projection_and_adds_actual_once(): void
    {
        Carbon::setTestNow($this->utc('2026-07-25 11:00:00'));
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $appointment = $this->appointment($owner, '2026-07-25 12:00:00', [[$employee, '100.00', 1]]);
        $item = $appointment->items()->firstOrFail();
        $service = Service::query()->create([
            'name' => 'Servicio de conversión',
            'duration_minutes' => 30,
            'price' => '100.00',
            'is_active' => true,
        ]);
        $item->service_id = $service->id;
        $item->save();

        $this->actingAs($owner)->get('/earnings?mode=both&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projection.appointments_count', 1)
                ->where('actual.completed_sales_count', 0));

        $this->post("/appointments/{$appointment->id}/checkout", [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'items' => [[
                'appointment_item_id' => $item->id,
                'service_id' => null,
                'quantity' => 1,
                'performed_by' => $employee->id,
            ]],
            'removed_appointment_item_ids' => [],
        ])->assertStatus(303);

        $this->get('/earnings?mode=both&period=today&date=2026-07-25')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projection.appointments_count', 0)
                ->where('actual.completed_sales_count', 1)
                ->where('actual.gross_revenue', '100.00'));
    }

    private function user(string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['is_active' => true, ...($name ? ['name' => $name] : [])]);
        $user->assignRole($role);

        return $user;
    }

    private function appointment(User $actor, string $localStart, array $lines, string $status = Appointment::STATUS_SCHEDULED): Appointment
    {
        $start = $this->utc($localStart);
        $totalCents = 0;
        foreach ($lines as [, $amount]) {
            $totalCents += (int) round(((float) $amount) * 100);
        }
        $appointment = new Appointment;
        $appointment->client_name = 'Clienta 4E';
        $appointment->assigned_to = $lines[0][0]->id;
        $appointment->scheduled_start = $start;
        $appointment->scheduled_end = $start->copy()->addMinutes(30 * count($lines));
        $appointment->expected_total = number_format($totalCents / 100, 2, '.', '');
        $appointment->expected_duration_minutes = 30 * count($lines);
        $appointment->status = $status;
        $appointment->created_by = $actor->id;
        $appointment->save();

        foreach ($lines as $index => [$employee, $amount, $quantity]) {
            $item = new AppointmentItem;
            $item->appointment_id = $appointment->id;
            $item->service_id = null;
            $item->assigned_to = $employee->id;
            $item->position = $index + 1;
            $item->service_name = 'Servicio '.($index + 1);
            $item->service_description = 'Descripción reservada';
            $item->duration_minutes = 30;
            $item->default_duration_minutes = 30;
            $item->scheduled_start = $start->copy()->addMinutes(30 * $index);
            $item->scheduled_end = $start->copy()->addMinutes(30 * ($index + 1));
            $item->unit_price = number_format(((float) $amount) / $quantity, 2, '.', '');
            $item->quantity = $quantity;
            $item->line_total = $amount;
            $item->save();
        }

        return $appointment;
    }

    private function deposit(Appointment $appointment, User $actor, string $amount, string $status = AppointmentDeposit::STATUS_PENDING, array $overrides = []): AppointmentDeposit
    {
        $deposit = new AppointmentDeposit;
        $deposit->appointment_id = $appointment->id;
        $deposit->amount = $amount;
        $deposit->payment_method = 'cash';
        $deposit->card_fee_rate = '0.00';
        $deposit->card_fee_amount = '0.00';
        $deposit->net_amount = $amount;
        $deposit->status = $status;
        $deposit->paid_at = $this->utc('2026-07-24 10:00:00');
        $deposit->recorded_by = $actor->id;
        $deposit->applied_amount = $overrides['applied_amount'] ?? '0.00';
        $deposit->refunded_amount = $overrides['refunded_amount'] ?? '0.00';
        $deposit->retained_amount = $overrides['retained_amount'] ?? '0.00';
        $deposit->resolved_at = $overrides['resolved_at'] ?? null;
        $deposit->resolved_by = $deposit->resolved_at ? $actor->id : null;
        $deposit->save();

        return $deposit;
    }

    private function refund(AppointmentDeposit $deposit, User $actor, string $amount, string $localTime): void
    {
        $refund = new AppointmentDepositRefund;
        $refund->appointment_deposit_id = $deposit->id;
        $refund->amount = $amount;
        $refund->refunded_at = $this->utc($localTime);
        $refund->refunded_by = $actor->id;
        $refund->operation_token = (string) Str::uuid();
        $refund->purpose = AppointmentDepositRefund::PURPOSE_TERMINAL;
        $refund->save();
    }

    private function sale(User $cashier, string $localTime, string $total, string $fee, array $methods): Sale
    {
        $sale = new Sale;
        $sale->sold_by = $cashier->id;
        $sale->sold_at = $this->utc($localTime);
        $sale->subtotal = $total;
        $sale->total = $total;
        $sale->total_services = 3;
        $sale->status = Sale::STATUS_COMPLETED;
        $sale->payment_method = end($methods);
        $sale->card_fee_rate = '4.00';
        $sale->card_fee_amount = $fee;
        $sale->net_amount = number_format(((float) $total) - ((float) $fee), 2, '.', '');
        $sale->checkout_token = (string) Str::uuid();
        $sale->request_hash = hash('sha256', (string) Str::uuid());
        $sale->save();
        $sale->sale_number = 'SL-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
        $sale->save();

        foreach ($methods as $index => $method) {
            $payment = new SalePayment;
            $payment->sale_id = $sale->id;
            $payment->type = $index === 0 && count($methods) > 1 ? SalePayment::TYPE_DEPOSIT_APPLIED : SalePayment::TYPE_FINAL_PAYMENT;
            $payment->method = $method;
            $payment->amount = number_format(((float) $total) / count($methods), 2, '.', '');
            $payment->card_fee_rate = $method === 'card' ? '4.00' : '0.00';
            $payment->card_fee_amount = '0.00';
            $payment->net_amount = $payment->amount;
            $payment->save();
        }

        return $sale;
    }

    private function saleItem(Sale $sale, User $performer, string $total, int $quantity, string $fee): void
    {
        $item = new SaleItem;
        $item->sale_id = $sale->id;
        $item->performed_by = $performer->id;
        $item->service_name = 'Servicio realizado';
        $item->duration_minutes = 30;
        $item->unit_price = number_format(((float) $total) / $quantity, 2, '.', '');
        $item->quantity = $quantity;
        $item->line_total = $total;
        $item->allocated_card_fee_amount = $fee;
        $item->net_line_amount = number_format(((float) $total) - ((float) $fee), 2, '.', '');
        $item->save();
    }

    private function utc(string $localTime): Carbon
    {
        return Carbon::parse($localTime, 'America/Tegucigalpa')->utc();
    }
}
