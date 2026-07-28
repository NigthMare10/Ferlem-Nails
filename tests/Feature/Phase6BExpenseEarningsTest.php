<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6BExpenseEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-27 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_available_result_uses_completed_sales_minus_pos_fee_and_recorded_expenses(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, 'card', '1000.00');
        $this->expense($owner, '200.00', 'cash', 'materiales-e-implementos');
        $canceled = $this->expense($owner, '300.00', 'card', 'transporte');
        $this->actingAs($owner)->post(route('expenses.cancel', $canceled), ['cancellation_reason' => 'No corresponde.']);

        $this->get('/earnings?period=today&date=2026-07-27&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->where('actual.gross_revenue', '1000.00')
            ->where('actual.pos_fee', '40.00')
            ->where('actual.net_income', '960.00')
            ->where('actual.paid_expenses', '200.00')
            ->where('actual.available_result', '760.00')
            ->where('expense_actual.expenses_count', 1)
            ->where('expense_actual.paid_expenses', '200.00')
            ->missing('expense_categories')
            ->missing('expense_daily')
            ->where('expense_payment_distribution.0.total', '200.00')
            ->where('expense_payment_distribution.1.total', '0.00')
            ->where('expense_payment_distribution.2.total', '0.00'));
    }

    public function test_expense_summary_respects_honduras_periods_without_removed_breakdowns(): void
    {
        $owner = $this->user('owner');
        $this->expense($owner, '10.00', 'cash', 'otros', '2026-07-01');
        $this->expense($owner, '20.00', 'transfer', 'otros', '2026-07-15');
        $this->expense($owner, '30.00', 'card', 'otros', '2026-08-01');

        $this->actingAs($owner)->get('/earnings?period=month&month=2026-07&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->where('expense_actual.paid_expenses', '30.00')
            ->missing('expense_daily')
            ->missing('expense_categories'));
    }

    public function test_sales_employee_and_payment_filters_do_not_hide_general_expenses(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $this->expense($owner, '75.00', 'transfer', 'servicios-publicos');

        $this->actingAs($owner)->get('/earnings?period=today&date=2026-07-27&mode=actual&employee_id='.$employee->id.'&payment_method=card')
            ->assertInertia(fn (Assert $page) => $page
                ->where('actual.gross_revenue', '0.00')
                ->where('expense_actual.paid_expenses', '75.00')
                ->where('actual.available_result', '-75.00'));
    }

    public function test_available_result_can_be_exactly_zero(): void
    {
        $owner = $this->user('owner');
        $this->sale($owner, 'cash', '100.00');
        $this->expense($owner, '100.00', 'cash', 'otros');

        $this->get('/earnings?period=today&date=2026-07-27&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->where('actual.net_income', '100.00')
            ->where('actual.paid_expenses', '100.00')
            ->where('actual.available_result', '0.00'));
    }

    public function test_administrator_with_expense_report_permission_sees_no_sales_props(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $this->expense($owner, '80.00', 'cash', 'alimentacion');

        $this->actingAs($administrator)->get('/earnings?period=today&date=2026-07-27')->assertInertia(fn (Assert $page) => $page
            ->component('Earnings/Index')
            ->where('canViewSales', false)
            ->where('canViewExpenses', true)
            ->where('expense_actual.paid_expenses', '80.00')
            ->missing('actual'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function expense(User $user, string $amount, string $method, string $categorySlug, string $date = '2026-07-27'): Expense
    {
        $this->actingAs($user)->post('/expenses', [
            'checkout_token' => (string) Str::uuid(), 'expense_date' => $date,
            'category_id' => ExpenseCategory::query()->where('slug', $categorySlug)->value('id'),
            'description' => 'Gasto de prueba', 'amount' => $amount,
            'payment_method' => $method, 'vendor' => null, 'employee_id' => null,
        ])->assertRedirect();

        return Expense::query()->latest('id')->firstOrFail();
    }

    private function sale(User $user, string $method, string $price): Sale
    {
        $service = Service::query()->create(['name' => 'Servicio reporte', 'duration_minutes' => 30, 'price' => $price, 'is_active' => true]);
        $this->actingAs($user)->post('/sales', [
            'checkout_token' => (string) Str::uuid(), 'payment_method' => $method,
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ])->assertRedirect();

        return Sale::query()->latest('id')->firstOrFail();
    }
}
