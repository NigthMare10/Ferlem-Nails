<?php

namespace Tests\Feature;

use App\Actions\Reports\BuildSalesSummaryAction;
use App\Models\Sale;
use App\Models\SaleAdditionalCharge;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase3BEarningsTest extends TestCase
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

    public function test_guest_is_redirected_and_inactive_user_cannot_access_earnings(): void
    {
        $this->get('/earnings')->assertRedirect(route('login'));

        $owner = $this->user('owner', ['is_active' => false]);
        $this->actingAs($owner)->get('/earnings')->assertRedirect(route('login'));
    }

    public function test_employee_without_report_permissions_receives_spanish_forbidden_page(): void
    {
        $user = $this->user('employee');

        $this->actingAs($user)->get('/earnings')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/Forbidden'));
    }

    public function test_owner_and_user_with_explicit_permission_can_access(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->get('/earnings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Earnings/Index')
                ->where('auth.navigation.earnings', true)
                ->where('period.timezone', 'America/Tegucigalpa'));

        $administrator = $this->user('administrator');
        $administrator->givePermissionTo(Permissions::REPORTS_SALES_VIEW);

        $this->actingAs($administrator)->get('/earnings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.navigation.earnings', true));
    }

    public function test_navigation_is_hidden_without_the_real_permission_and_report_can_be_an_administrator_landing(): void
    {
        $employee = $this->user('employee');
        $this->actingAs($employee)->get('/sales/new')
            ->assertInertia(fn (Assert $page) => $page->where('auth.navigation.earnings', false));

        $administrator = $this->user('administrator');
        Role::findByName('administrator')->syncPermissions(Permissions::REPORTS_SALES_VIEW);

        $this->actingAs($administrator)->get('/')->assertRedirect(route('earnings.index'));
    }

    public function test_today_uses_honduras_midnight_boundaries(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-18 23:59:59', '10.00', 1);
        $this->sale($owner, '2026-07-19 00:00:00', '20.00', 2);
        $this->sale($owner, '2026-07-19 23:59:59', '30.00', 3);
        $this->sale($owner, '2026-07-20 00:00:00', '40.00', 4);

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.start_date', '2026-07-19')
                ->where('period.end_date', '2026-07-19')
                ->where('summary.total_sold', '50.00')
                ->where('summary.sales_count', 2)
                ->where('summary.services_count', 5)
                ->where('daily.0.date', '2026-07-19'));
    }

    public function test_week_starts_on_monday_and_excludes_adjacent_weeks(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-12 23:59:59', '10.00', 1);
        $this->sale($owner, '2026-07-13 00:00:00', '20.00', 1);
        $this->sale($owner, '2026-07-19 23:59:59', '30.00', 1);
        $this->sale($owner, '2026-07-20 00:00:00', '40.00', 1);

        $this->actingAs($owner)->get('/earnings?period=week&date=2026-07-15')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.start_date', '2026-07-13')
                ->where('period.end_date', '2026-07-19')
                ->where('period.week_starts_on', 'monday')
                ->where('summary.total_sold', '50.00'));
    }

    public function test_month_uses_first_and_last_local_day(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-06-30 23:59:59', '10.00', 1);
        $this->sale($owner, '2026-07-01 00:00:00', '20.00', 1);
        $this->sale($owner, '2026-07-31 23:59:59', '30.00', 1);
        $this->sale($owner, '2026-08-01 00:00:00', '40.00', 1);

        $this->actingAs($owner)->get('/earnings?period=month&date=2026-07-18')
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.start_date', '2026-07-01')
                ->where('period.end_date', '2026-07-31')
                ->where('summary.total_sold', '50.00'));
    }

    public function test_custom_period_validates_required_invalid_inverted_and_excessive_dates(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);

        $this->from('/earnings')->get('/earnings?period=custom')
            ->assertRedirect('/earnings')
            ->assertSessionHasErrors(['date_from', 'date_to']);
        $this->from('/earnings')->get('/earnings?period=custom&date_from=no-es-fecha&date_to=2026-07-19')
            ->assertSessionHasErrors('date_from');
        $this->from('/earnings')->get('/earnings?period=custom&date_from=2026-07-20&date_to=2026-07-19')
            ->assertSessionHasErrors('date_to');
        $this->from('/earnings')->get('/earnings?period=custom&date_from=2025-01-01&date_to=2026-01-02')
            ->assertSessionHasErrors(['date_to' => 'El rango personalizado no puede superar 366 días.']);
    }

    public function test_custom_period_accepts_366_inclusive_days(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)
            ->get('/earnings?period=custom&date_from=2025-01-01&date_to=2026-01-01')
            ->assertOk()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_employee_filter_and_all_aggregates_are_exact_without_mixing_performers(): void
    {
        $owner = $this->user('owner', ['name' => 'Owner']);
        $employee = $this->user('employee', ['name' => 'Empleado']);
        $this->item($this->sale($owner, '2026-07-19 09:00:00', '100.00', 2), '100.00', 2, $owner);
        $this->item($this->sale($owner, '2026-07-19 10:00:00', '50.00', 1), '50.00', 1, $owner);
        $this->item($this->sale($employee, '2026-07-19 11:00:00', '250.00', 3), '250.00', 3, $employee);

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '400.00')
                ->where('summary.sales_count', 3)
                ->where('summary.services_count', 6)
                ->where('summary.average_sale', '133.33')
                ->has('employees', 2)
                ->where('employees.0.name', 'Empleado')
                ->where('employees.0.total_sold', '250.00')
                ->where('employees.1.name', 'Owner')
                ->where('employees.1.total_sold', '150.00'));

        $this->get('/earnings?period=today&date=2026-07-19&employee_id='.$owner->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '150.00')
                ->where('summary.sales_count', 2)
                ->where('summary.services_count', 3)
                ->where('summary.average_sale', '75.00')
                ->has('employees', 1)
                ->where('employees.0.id', $owner->id));
    }

    public function test_adjusted_shared_sales_attribute_all_income_and_reconcile_employee_totals(): void
    {
        $owner = $this->user('owner', ['name' => 'Melany']);
        $valery = $this->user('employee', ['name' => 'Valery']);
        $sales = [
            ['total' => '1050.00', 'fee' => '42.00', 'net' => '1008.00', 'payment' => Sale::PAYMENT_METHOD_CARD, 'items' => [['350.00', 1, $owner], ['350.00', 1, $valery]], 'charge' => '350.00'],
            ['total' => '1150.00', 'fee' => '46.00', 'net' => '1104.00', 'payment' => Sale::PAYMENT_METHOD_CARD, 'items' => [['780.00', 1, $owner], ['350.00', 1, $valery]], 'charge' => '20.00'],
            ['total' => '1100.00', 'fee' => '0.00', 'net' => '1100.00', 'payment' => Sale::PAYMENT_METHOD_TRANSFER, 'items' => [['700.00', 1, $owner], ['350.00', 1, $valery]], 'charge' => '50.00'],
            ['total' => '350.00', 'fee' => '14.00', 'net' => '336.00', 'payment' => Sale::PAYMENT_METHOD_CARD, 'items' => [['350.00', 1, $owner]], 'charge' => null],
        ];
        foreach ($sales as $index => $data) {
            $sale = $this->sale($owner, '2026-07-29 16:0'.$index.':00', $data['total'], array_sum(array_column($data['items'], 1)));
            Sale::query()->whereKey($sale)->update([
                'card_fee_rate' => $data['fee'] === '0.00' ? '0.00' : '4.00',
                'card_fee_amount' => $data['fee'],
                'net_amount' => $data['net'],
            ]);
            $sale->refresh();
            foreach ($data['items'] as [$amount, $quantity, $performer]) {
                $this->item($sale, $amount, $quantity, $performer);
            }
            if ($data['charge']) {
                SaleAdditionalCharge::query()->forceCreate(['sale_id' => $sale->id, 'name' => 'Cargo', 'description' => 'Cargo', 'amount' => $data['charge']]);
            }
            SalePayment::query()->forceCreate(['sale_id' => $sale->id, 'type' => SalePayment::TYPE_FINAL_PAYMENT, 'method' => $data['payment'], 'amount' => $data['total'], 'card_fee_rate' => $sale->card_fee_rate, 'card_fee_amount' => $data['fee'], 'net_amount' => $sale->net_amount]);
        }

        $report = app(BuildSalesSummaryAction::class)->execute(['period' => 'today', 'date' => '2026-07-29']);

        $this->assertSame('3650.00', $report['actual']['gross_revenue']);
        $this->assertSame('102.00', $report['actual']['pos_fee']);
        $this->assertSame('3548.00', $report['actual']['net_income']);
        $this->assertSame('3650.00', Money::fromCents(collect($report['payment_distribution'])->sum(fn (array $payment) => Money::toCents($payment['amount']))));
        $this->assertSame('3650.00', Money::fromCents(collect($report['employees'])->sum(fn (array $employee) => Money::toCents($employee['total_sold']))));
        $this->assertSame('102.00', Money::fromCents(collect($report['employees'])->sum(fn (array $employee) => Money::toCents($employee['card_fee_amount']))));
        $this->assertSame('3548.00', Money::fromCents(collect($report['employees'])->sum(fn (array $employee) => Money::toCents($employee['net_amount']))));
        $this->assertSame('2402.14', $report['employees'][0]['total_sold']);
        $this->assertSame('66.75', $report['employees'][0]['card_fee_amount']);
        $this->assertSame('2335.39', $report['employees'][0]['net_amount']);
        $this->assertSame('1247.86', $report['employees'][1]['total_sold']);
        $this->assertSame(4, $report['actual']['completed_sales_count']);
        $this->assertSame(7, $report['actual']['performed_services_count']);
        $this->assertSame(4, $report['employees'][0]['services_count']);
        $this->assertSame(3, $report['employees'][1]['services_count']);
        $this->assertArrayNotHasKey('sales_count', $report['employees'][0]);
        $this->assertArrayNotHasKey('unattributable_gross', $report['actual']);

        $earningsPage = file_get_contents(resource_path('js/Pages/Earnings/Index.vue'));
        $pdf = file_get_contents(resource_path('views/pdf/daily-close.blade.php'));
        $this->assertStringNotContainsString('Ventas atendidas', $earningsPage);
        $this->assertStringNotContainsString('Ventas atendidas', $pdf);
        $this->assertStringNotContainsString('Ingresos no atribuibles', $earningsPage.$pdf);

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-29')
            ->assertInertia(fn (Assert $page) => $page
                ->missing('employees.0.sales_count')
                ->missing('employees.0.average_sale'));
    }

    public function test_fabricated_employee_id_is_rejected_and_report_exposes_no_individual_sales(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-19 09:00:00', '100.00', 1);

        $this->actingAs($owner)->from('/earnings')
            ->get('/earnings?period=today&date=2026-07-19&employee_id=999999')
            ->assertSessionHasErrors('employee_id');

        $this->get('/earnings?period=today&date=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->missing('sales')
                ->missing('employees.0.email')
                ->missing('employees.0.sale_number')
                ->missing('daily.0.sale_id'));
    }

    public function test_only_completed_sales_inside_period_count_and_item_joins_do_not_duplicate_total(): void
    {
        $owner = $this->user('owner');
        $included = $this->sale($owner, '2026-07-19 10:00:00', '100.00', 3);
        $this->item($included, '40.00', 1);
        $this->item($included, '60.00', 2);
        DB::statement('PRAGMA ignore_check_constraints = ON');
        $this->sale($owner, '2026-07-19 11:00:00', '999.00', 1, 'pending');
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        $this->sale($owner, '2026-07-20 10:00:00', '500.00', 1);

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '100.00')
                ->where('summary.sales_count', 1)
                ->where('summary.services_count', 3));
    }

    public function test_service_count_matches_sale_item_quantities_for_normal_sales(): void
    {
        $owner = $this->user('owner');
        $sale = $this->sale($owner, '2026-07-19 10:00:00', '75.00', 5);
        $this->item($sale, '30.00', 2);
        $this->item($sale, '45.00', 3);

        $report = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'today',
            'date' => '2026-07-19',
        ]);

        $this->assertSame((int) $sale->items()->sum('quantity'), $report['summary']['services_count']);
    }

    public function test_daily_summary_groups_by_honduras_date_and_orders_newest_first(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-18 23:59:59', '10.00', 1);
        $this->sale($owner, '2026-07-19 00:00:00', '20.00', 2);
        $this->sale($owner, '2026-07-19 18:00:00', '30.00', 3);

        $this->actingAs($owner)->get('/earnings?period=custom&date_from=2026-07-18&date_to=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->has('daily', 2)
                ->where('daily.0.date', '2026-07-19')
                ->where('daily.0.total_sold', '50.00')
                ->where('daily.0.sales_count', 2)
                ->where('daily.0.services_count', 5)
                ->where('daily.1.date', '2026-07-18')
                ->where('daily.1.total_sold', '10.00'));
    }

    public function test_daily_summary_returns_only_completed_active_days_for_month_custom_employee_and_payment_filters(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $cash = $this->sale($owner, '2026-07-01 10:00:00', '100.00', 1);
        $this->item($cash, '100.00', 1, $owner);
        $this->payment($cash, Sale::PAYMENT_METHOD_CASH);
        $canceledOnly = $this->sale($owner, '2026-07-02 10:00:00', '900.00', 1, Sale::STATUS_CANCELED);
        $this->item($canceledOnly, '900.00', 1, $owner);
        $this->payment($canceledOnly, Sale::PAYMENT_METHOD_CASH);
        $card = $this->sale($employee, '2026-07-03 10:00:00', '200.00', 2);
        $this->item($card, '200.00', 2, $employee);
        $this->payment($card, Sale::PAYMENT_METHOD_CARD);
        $canceledSameDay = $this->sale($owner, '2026-07-03 12:00:00', '800.00', 1, Sale::STATUS_CANCELED);
        $this->item($canceledSameDay, '800.00', 1, $owner);
        $this->payment($canceledSameDay, Sale::PAYMENT_METHOD_CARD);

        $monthly = app(BuildSalesSummaryAction::class)->execute(['period' => 'month', 'month' => '2026-07']);
        $this->assertSame('300.00', $monthly['summary']['total_sold']);
        $this->assertSame(['2026-07-03', '2026-07-01'], array_column($monthly['daily'], 'date'));
        $this->assertSame('200.00', $monthly['daily'][0]['total_sold']);
        $this->assertSame('100.00', $monthly['daily'][1]['total_sold']);

        $custom = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'custom',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-02',
        ]);
        $this->assertSame(['2026-07-01'], array_column($custom['daily'], 'date'));

        $ownerReport = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'month',
            'month' => '2026-07',
            'employee_id' => $owner->id,
        ]);
        $this->assertSame(['2026-07-01'], array_column($ownerReport['daily'], 'date'));

        $cardReport = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'month',
            'month' => '2026-07',
            'payment_method' => Sale::PAYMENT_METHOD_CARD,
        ]);
        $this->assertSame(['2026-07-03'], array_column($cardReport['daily'], 'date'));
    }

    public function test_period_without_sales_returns_zero_and_empty_collections(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-19')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_sold', '0.00')
                ->where('summary.sales_count', 0)
                ->where('summary.services_count', 0)
                ->where('summary.average_sale', '0.00')
                ->has('employees', 0)
                ->has('daily', 0));
    }

    public function test_report_chunks_more_than_one_thousand_completed_sales_without_changing_outputs(): void
    {
        $owner = $this->user('owner', ['name' => 'Owner']);
        $firstId = ((int) Sale::query()->max('id')) + 1;
        $now = now('UTC');
        $sales = [];
        $items = [];
        $payments = [];

        for ($offset = 0; $offset < 1005; $offset++) {
            $id = $firstId + $offset;
            $sequence = $offset + 1;
            $sales[] = [
                'id' => $id,
                'sale_number' => sprintf('BULK-%06d', $sequence),
                'sold_by' => $owner->id,
                'sold_at' => '2026-07-19 16:00:00',
                'subtotal' => '1.00',
                'total' => '1.00',
                'total_services' => 1,
                'status' => Sale::STATUS_COMPLETED,
                'payment_method' => Sale::PAYMENT_METHOD_CASH,
                'card_fee_rate' => '0.00',
                'card_fee_amount' => '0.00',
                'net_amount' => '1.00',
                'checkout_token' => sprintf('00000000-0000-4000-8000-%012d', $sequence),
                'request_hash' => hash('sha256', (string) $sequence),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $items[] = [
                'sale_id' => $id,
                'service_id' => null,
                'performed_by' => $owner->id,
                'appointment_item_id' => null,
                'position' => 1,
                'service_name' => 'Servicio masivo',
                'duration_minutes' => 30,
                'unit_price' => '1.00',
                'quantity' => 1,
                'line_total' => '1.00',
                'allocated_card_fee_amount' => '0.00',
                'net_line_amount' => '1.00',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $payments[] = [
                'sale_id' => $id,
                'type' => SalePayment::TYPE_FINAL_PAYMENT,
                'method' => Sale::PAYMENT_METHOD_CASH,
                'amount' => '1.00',
                'card_fee_rate' => '0.00',
                'card_fee_amount' => '0.00',
                'net_amount' => '1.00',
                'appointment_deposit_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($sales, 50) as $chunk) {
            DB::table('sales')->insert($chunk);
        }
        foreach (array_chunk($items, 50) as $chunk) {
            DB::table('sale_items')->insert($chunk);
        }
        foreach (array_chunk($payments, 50) as $chunk) {
            DB::table('sale_payments')->insert($chunk);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $report = app(BuildSalesSummaryAction::class)->execute(['period' => 'today', 'date' => '2026-07-19']);
        $unfilteredQueries = collect(DB::getQueryLog());

        $this->assertSame('1005.00', $report['summary']['total_sold']);
        $this->assertSame('0.00', $report['summary']['card_fee_amount']);
        $this->assertSame('1005.00', $report['summary']['net_amount']);
        $this->assertSame(1005, $report['summary']['sales_count']);
        $this->assertSame(1005, $report['summary']['services_count']);
        $this->assertSame('1.00', $report['summary']['average_sale']);
        $this->assertSame(1005, $report['payment_distribution'][0]['payments_count']);
        $this->assertSame('1005.00', $report['payment_distribution'][0]['amount']);
        $this->assertSame(1005, $report['daily'][0]['sales_count']);
        $this->assertSame('1005.00', $report['daily'][0]['total_sold']);
        $this->assertLessThanOrEqual(501, $unfilteredQueries->max(fn (array $query) => count($query['bindings'])));

        DB::flushQueryLog();
        $employeeReport = app(BuildSalesSummaryAction::class)->execute([
            'period' => 'today',
            'date' => '2026-07-19',
            'employee_id' => $owner->id,
        ]);
        $employeeQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame('1005.00', $employeeReport['summary']['total_sold']);
        $this->assertSame(1005, $employeeReport['summary']['sales_count']);
        $this->assertSame(1005, $employeeReport['summary']['services_count']);
        $this->assertSame('1005.00', $employeeReport['employees'][0]['total_sold']);
        $this->assertTrue($employeeQueries->contains(fn (array $query) => preg_match('/sale_items.*sale_id.*\bin\s*\(/i', $query['query']) === 1));
        $this->assertLessThanOrEqual(501, $employeeQueries->max(fn (array $query) => count($query['bindings'])));
    }

    public function test_report_uses_three_sales_queries_and_never_queries_cash_sessions(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-19 10:00:00', '100.00', 1);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(BuildSalesSummaryAction::class)->execute(['period' => 'today', 'date' => '2026-07-19']);

        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertGreaterThanOrEqual(4, $queries->count());
        $this->assertFalse($queries->contains(fn (string $query) => str_contains(strtolower($query), 'cash_sessions')));
        $this->assertFalse(Schema::hasTable('daily_closures'));
        $this->assertFalse(Route::has('cash.close'));
    }

    public function test_report_permission_seeding_is_idempotent_and_assignments_are_exact(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Permission::query()->where('name', Permissions::REPORTS_SALES_VIEW)->count());
        $this->assertTrue(Role::findByName('owner')->hasPermissionTo(Permissions::REPORTS_SALES_VIEW));
        $this->assertFalse(Role::findByName('administrator')->hasPermissionTo(Permissions::REPORTS_SALES_VIEW));
        $this->assertFalse(Role::findByName('employee')->hasPermissionTo(Permissions::REPORTS_SALES_VIEW));
        $this->assertSame([Permissions::REPORTS_EXPENSES_VIEW, Permissions::REPORTS_SALES_VIEW], Permission::query()
            ->where('name', 'like', 'reports.%')
            ->orderBy('name')
            ->pluck('name')
            ->all());
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function sale(
        User $seller,
        string $hondurasDateTime,
        string $total,
        int $services,
        string $status = Sale::STATUS_COMPLETED,
    ): Sale {
        $sale = new Sale;
        $sale->sold_by = $seller->id;
        $sale->sold_at = Carbon::parse($hondurasDateTime, 'America/Tegucigalpa')->utc();
        $sale->subtotal = $total;
        $sale->total = $total;
        $sale->total_services = $services;
        $sale->status = $status;
        $sale->payment_method = Sale::PAYMENT_METHOD_CASH;
        $sale->card_fee_rate = '0.00';
        $sale->card_fee_amount = '0.00';
        $sale->net_amount = $total;
        $sale->checkout_token = (string) Str::uuid();
        $sale->request_hash = hash('sha256', (string) Str::uuid());
        $sale->save();
        $sale->sale_number = 'SL-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
        $sale->save();

        return $sale;
    }

    private function item(Sale $sale, string $lineTotal, int $quantity, ?User $performer = null): SaleItem
    {
        $item = new SaleItem;
        $item->sale_id = $sale->id;
        $item->service_id = null;
        $item->performed_by = $performer?->id ?? $sale->sold_by;
        $item->service_name = 'Servicio '.$sale->items()->count();
        $item->duration_minutes = 30;
        $item->unit_price = $lineTotal;
        $item->quantity = $quantity;
        $item->line_total = $lineTotal;
        $item->save();

        return $item;
    }

    private function payment(Sale $sale, string $method): SalePayment
    {
        $payment = new SalePayment;
        $payment->sale_id = $sale->id;
        $payment->type = SalePayment::TYPE_FINAL_PAYMENT;
        $payment->method = $method;
        $payment->amount = $sale->total;
        $payment->card_fee_rate = $sale->card_fee_rate;
        $payment->card_fee_amount = $sale->card_fee_amount;
        $payment->net_amount = $sale->net_amount;
        $payment->save();

        return $payment;
    }
}
