<?php

namespace Tests\Feature;

use App\Actions\Reports\BuildSalesSummaryAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase3B1CardPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_payment_method_is_required_and_only_accepts_cash_or_card(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $payload = $this->payload($service, Sale::PAYMENT_METHOD_CASH);
        unset($payload['payment_method']);

        $this->actingAs($employee)->post('/sales', $payload)
            ->assertSessionHasErrors(['payment_method' => 'Selecciona el método de pago.']);

        $payload['payment_method'] = 'transfer';
        $this->post('/sales', $payload)
            ->assertSessionHasErrors(['payment_method' => 'El método de pago debe ser efectivo o tarjeta.']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cash_sale_saves_zero_fee_and_net_equal_to_full_total(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '1000.00']);

        $this->actingAs($employee)->post('/sales', $this->payload($service, Sale::PAYMENT_METHOD_CASH))
            ->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('0.00', $sale->card_fee_rate);
        $this->assertSame('0.00', $sale->card_fee_amount);
        $this->assertSame('1000.00', $sale->total);
        $this->assertSame('1000.00', $sale->net_amount);
    }

    public function test_card_sale_saves_four_percent_fee_and_net_without_reducing_customer_total(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '1000.00']);
        $payload = $this->payload($service, Sale::PAYMENT_METHOD_CARD) + [
            'subtotal' => '1.00',
            'total' => '1.00',
            'card_fee_rate' => '99.00',
            'card_fee_amount' => '999.00',
            'net_amount' => '0.01',
        ];

        $this->actingAs($employee)->post('/sales', $payload)->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('card', $sale->payment_method);
        $this->assertSame('4.00', $sale->card_fee_rate);
        $this->assertSame('40.00', $sale->card_fee_amount);
        $this->assertSame('960.00', $sale->net_amount);
        $this->assertSame('1000.00', $sale->subtotal);
        $this->assertSame('1000.00', $sale->total);
    }

    public function test_card_fee_rounds_to_nearest_cent_without_float_calculation(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '10.13']);

        $sale = app(CreateSaleAction::class)->execute(
            $employee,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CARD,
        );

        $this->assertSame('10.13', $sale->total);
        $this->assertSame('0.41', $sale->card_fee_amount);
        $this->assertSame('9.72', $sale->net_amount);
    }

    public function test_confirmation_token_cannot_change_payment_method_after_sale_is_saved(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $token = (string) Str::uuid();

        $this->actingAs($employee)->post('/sales', $this->payload($service, Sale::PAYMENT_METHOD_CASH, $token))
            ->assertStatus(303);
        $this->post('/sales', $this->payload($service, Sale::PAYMENT_METHOD_CARD, $token))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('sales', 1);
        $sale = Sale::query()->firstOrFail();
        $this->assertSame(Sale::PAYMENT_METHOD_CASH, $sale->payment_method);
        $this->assertSame('0.00', $sale->card_fee_amount);
        $this->assertSame($sale->total, $sale->net_amount);
    }

    public function test_card_receipt_keeps_full_total_and_never_exposes_internal_fee_or_net(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '1000.00']);
        $sale = app(CreateSaleAction::class)->execute(
            $employee,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CARD,
        );

        $this->actingAs($employee)->get(route('sales.receipt', $sale))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Receipt')
                ->where('sale.total', '1000.00')
                ->where('sale.payment_method', 'card')
                ->where('sale.payment_method_label', 'Tarjeta')
                ->missing('sale.card_fee_rate')
                ->missing('sale.card_fee_amount')
                ->missing('sale.net_amount'));
    }

    public function test_earnings_sum_gross_fee_and_net_by_employee_and_honduras_day(): void
    {
        $owner = $this->user('owner', ['name' => 'Owner']);
        $employee = $this->user('employee', ['name' => 'Empleado']);
        $this->reportSale($owner, '2026-07-19 09:00:00', '1000.00', 2, 'cash', '0.00', '1000.00');
        $this->reportSale($employee, '2026-07-19 10:00:00', '1000.00', 3, 'card', '40.00', '960.00');

        $report = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'today',
            'date' => '2026-07-19',
        ]);

        $this->assertSame('2000.00', $report['summary']['total_sold']);
        $this->assertSame('40.00', $report['summary']['card_fee_amount']);
        $this->assertSame('1960.00', $report['summary']['net_amount']);
        $this->assertSame(2, $report['summary']['sales_count']);
        $this->assertSame(5, $report['summary']['services_count']);
        $this->assertSame('1000.00', $report['summary']['average_sale']);
        $this->assertCount(2, $report['employees']);
        $this->assertSame('40.00', collect($report['employees'])->firstWhere('name', 'Empleado')['card_fee_amount']);
        $this->assertSame('960.00', collect($report['employees'])->firstWhere('name', 'Empleado')['net_amount']);
        $this->assertSame('40.00', $report['daily'][0]['card_fee_amount']);
        $this->assertSame('1960.00', $report['daily'][0]['net_amount']);
    }

    public function test_payment_method_filters_combine_with_employee_and_preserve_query_contract(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $this->reportSale($owner, '2026-07-19 09:00:00', '100.00', 1, 'cash', '0.00', '100.00');
        $this->reportSale($owner, '2026-07-19 10:00:00', '200.00', 1, 'card', '8.00', '192.00');
        $this->reportSale($employee, '2026-07-19 11:00:00', '300.00', 1, 'card', '12.00', '288.00');

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-19&payment_method=cash')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.payment_method', 'cash')
                ->where('summary.total_sold', '100.00')
                ->where('summary.card_fee_amount', '0.00')
                ->where('summary.net_amount', '100.00'));

        $this->get('/earnings?period=today&date=2026-07-19&payment_method=card')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '500.00')
                ->where('summary.card_fee_amount', '20.00')
                ->where('summary.net_amount', '480.00'));

        $this->get('/earnings?period=today&date=2026-07-19&payment_method=card&employee_id='.$owner->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '200.00')
                ->where('summary.card_fee_amount', '8.00')
                ->where('summary.net_amount', '192.00')
                ->has('employees', 1)
                ->where('employees.0.id', $owner->id));

        $this->from('/earnings')->get('/earnings?payment_method=transfer')
            ->assertSessionHasErrors('payment_method');
    }

    public function test_earnings_use_the_fee_snapshot_instead_of_recalculating_with_current_rate(): void
    {
        $owner = $this->user('owner');
        $this->reportSale($owner, '2026-07-19 09:00:00', '1000.00', 1, 'card', '25.00', '975.00', '2.50');

        $report = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'today',
            'date' => '2026-07-19',
        ]);

        $this->assertSame('25.00', $report['summary']['card_fee_amount']);
        $this->assertSame('975.00', $report['summary']['net_amount']);
        $this->assertSame('2.50', Sale::query()->firstOrFail()->card_fee_rate);
        $this->assertSame('4.00', Sale::CARD_FEE_RATE);
    }

    public function test_employee_still_cannot_open_financial_report_and_report_does_not_query_cash(): void
    {
        $employee = $this->user('employee');
        $this->actingAs($employee)->get('/earnings')->assertForbidden();

        $owner = $this->user('owner');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner)->get('/earnings?payment_method=card')->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertFalse($queries->contains(fn (string $query) => str_contains(strtolower($query), 'cash_sessions')));
        $this->assertFalse(Schema::hasTable('daily_closures'));
    }

    public function test_seeders_remain_idempotent_without_new_permissions(): void
    {
        $permissionCount = Permission::query()->count();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame(1, Permission::query()->where('name', 'reports.sales.view')->count());
        $this->assertSame(0, Permission::query()->where('name', 'like', 'payments.%')->count());
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
            'name' => 'Servicio de prueba',
            'description' => 'Descripción',
            'duration_minutes' => 45,
            'price' => '250.00',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function payload(Service $service, string $paymentMethod, ?string $token = null): array
    {
        return [
            'checkout_token' => $token ?? (string) Str::uuid(),
            'payment_method' => $paymentMethod,
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ];
    }

    private function reportSale(
        User $seller,
        string $hondurasDateTime,
        string $total,
        int $services,
        string $paymentMethod,
        string $fee,
        string $net,
        ?string $rate = null,
    ): Sale {
        $sale = new Sale;
        $sale->sold_by = $seller->id;
        $sale->sold_at = Carbon::parse($hondurasDateTime, 'America/Tegucigalpa')->utc();
        $sale->subtotal = $total;
        $sale->total = $total;
        $sale->total_services = $services;
        $sale->status = Sale::STATUS_COMPLETED;
        $sale->payment_method = $paymentMethod;
        $sale->card_fee_rate = $paymentMethod === Sale::PAYMENT_METHOD_CARD ? ($rate ?? Sale::CARD_FEE_RATE) : '0.00';
        $sale->card_fee_amount = $fee;
        $sale->net_amount = $net;
        $sale->checkout_token = (string) Str::uuid();
        $sale->request_hash = hash('sha256', (string) Str::uuid());
        $sale->save();
        $sale->sale_number = 'SL-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
        $sale->save();

        $item = new SaleItem;
        $item->sale_id = $sale->id;
        $item->performed_by = $seller->id;
        $item->service_name = 'Servicio histórico';
        $item->duration_minutes = 45;
        $item->unit_price = number_format(((float) $total) / $services, 2, '.', '');
        $item->quantity = $services;
        $item->line_total = $total;
        $item->allocated_card_fee_amount = $fee;
        $item->net_line_amount = $net;
        $item->save();

        $payment = new SalePayment;
        $payment->sale_id = $sale->id;
        $payment->type = SalePayment::TYPE_FINAL_PAYMENT;
        $payment->method = $paymentMethod;
        $payment->amount = $total;
        $payment->card_fee_rate = $sale->card_fee_rate;
        $payment->card_fee_amount = $fee;
        $payment->net_amount = $net;
        $payment->save();

        return $sale;
    }
}
