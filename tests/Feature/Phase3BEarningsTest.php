<?php

namespace Tests\Feature;

use App\Actions\Reports\BuildSalesSummaryAction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
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

    public function test_employee_and_administrator_without_permission_receive_spanish_forbidden_page(): void
    {
        foreach (['employee', 'administrator'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get('/earnings')
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page->component('Errors/Forbidden'));
        }
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
                ->has('daily', 1)
                ->where('daily.0.date', '2026-07-19')
                ->where('daily.0.total_sold', '0.00'));
    }

    public function test_report_uses_three_sales_queries_and_never_queries_cash_sessions(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, '2026-07-19 10:00:00', '100.00', 1);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(BuildSalesSummaryAction::class)->execute(['period' => 'today', 'date' => '2026-07-19']);

        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertCount(4, $queries);
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
        $this->assertSame([Permissions::REPORTS_SALES_VIEW], Permission::query()
            ->where('name', 'like', 'reports.%')
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
}
