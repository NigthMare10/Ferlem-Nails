<?php

namespace Tests\Feature;

use App\Actions\Payroll\ConfigureCompensationProfileAction;
use App\Actions\Payroll\ProcessPayrollAction;
use App\Models\EmployeeCompensationProfile;
use App\Models\Expense;
use App\Models\PayrollEvent;
use App\Models\PayrollObligation;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class PayrollAutomationRestructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_employee_requires_labor_data_and_creation_is_atomic(): void
    {
        $owner = $this->user('owner');
        $base = ['name' => 'Empleada', 'email' => 'empleada@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'employee', 'is_active' => true];
        $this->actingAs($owner)->post('/configuration/users', $base)->assertSessionHasErrors(['monthly_salary', 'contract_start_date']);

        Event::listen('eloquent.creating: '.PayrollEvent::class, fn () => throw new RuntimeException('rollback laboral'));
        try {
            $this->post('/configuration/users', [...$base, ...$this->laborData()]);
        } finally {
            Event::forget('eloquent.creating: '.PayrollEvent::class);
        }
        $this->assertDatabaseMissing('users', ['email' => 'empleada@example.com']);

        $this->post('/configuration/users', [...$base, ...$this->laborData()])->assertSessionHas('success');
        $employee = User::query()->where('email', 'empleada@example.com')->firstOrFail();
        $profile = EmployeeCompensationProfile::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('15000.00', $profile->monthly_salary);
        $this->assertSame('2026-01-01', $profile->contract_start_date->toDateString());
        $this->assertTrue($profile->is_indefinite);
        $this->assertTrue($profile->auto_generate_payroll_expense);
        $this->assertSame('transfer', $profile->default_payment_method);
    }

    public function test_finite_contract_validation_and_salary_update_preserve_history(): void
    {
        CarbonImmutable::setTestNow('2026-03-01 12:00:00');
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, $this->profileData());

        $payload = [...$this->laborData(), 'name' => $employee->name, 'email' => $employee->email, 'role' => 'employee', 'is_indefinite' => false, 'contract_end_date' => '2025-12-31'];
        $this->actingAs($owner)->put("/configuration/users/{$employee->id}", $payload)->assertSessionHasErrors('contract_end_date');
        $this->put("/configuration/users/{$employee->id}", [...$payload, 'monthly_salary' => '18000.00', 'contract_end_date' => '2026-12-31'])->assertSessionHas('success');

        $profiles = EmployeeCompensationProfile::query()->where('user_id', $employee->id)->orderBy('effective_from')->get();
        $this->assertCount(2, $profiles);
        $this->assertSame('15000.00', $profiles[0]->monthly_salary);
        $this->assertSame('18000.00', $profiles[1]->monthly_salary);
        $this->assertSame('2026-12-31', $profiles[1]->contract_end_date->toDateString());
        CarbonImmutable::setTestNow();
    }

    public function test_process_payroll_backfills_due_installments_and_is_idempotent(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, $this->profileData());

        $results = app(ProcessPayrollAction::class)->execute(CarbonImmutable::parse('2026-03-31', 'America/Tegucigalpa'), $owner);
        $this->assertCount(6, $results->where('status', 'paid'), $results->toJson());
        $this->assertDatabaseCount('payroll_obligations', 6);
        $this->assertDatabaseCount('expenses', 6);
        $this->assertSame(6, PayrollObligation::query()->where('status', 'paid')->count());
        $this->assertSame(0, PayrollObligation::query()->whereNull('expense_id')->count());
        $this->assertTrue(Expense::query()->get()->every(fn (Expense $expense) => $expense->payment_method === 'transfer'));

        app(ProcessPayrollAction::class)->execute(CarbonImmutable::parse('2026-03-31', 'America/Tegucigalpa'), $owner);
        $this->assertDatabaseCount('payroll_obligations', 6);
        $this->assertDatabaseCount('expenses', 6);
    }

    public function test_real_month_end_and_contract_end_are_respected(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, [...$this->profileData(), 'contract_end_date' => '2026-02-28', 'is_indefinite' => false, 'effective_to' => '2026-02-28']);

        app(ProcessPayrollAction::class)->execute(CarbonImmutable::parse('2026-03-31', 'America/Tegucigalpa'), $owner);
        $this->assertDatabaseCount('payroll_obligations', 4);
        $this->assertSame('2026-02-28', PayrollObligation::query()->where('installment', 'second')->latest('scheduled_date')->firstOrFail()->scheduled_date->toDateString());
        $this->assertDatabaseMissing('payroll_obligations', ['period_month' => 3]);

        $leapEmployee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $leapEmployee, [...$this->profileData(), 'effective_from' => '2028-02-01', 'contract_start_date' => '2028-02-01']);
        app(ProcessPayrollAction::class)->execute(CarbonImmutable::parse('2028-02-29', 'America/Tegucigalpa'), $owner);
        $this->assertSame('2028-02-29', PayrollObligation::query()->where('user_id', $leapEmployee->id)->where('installment', 'second')->firstOrFail()->scheduled_date->toDateString());
    }

    public function test_invalid_configuration_records_issue_without_partial_expense(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, [...$this->profileData(), 'default_payment_method' => null]);

        app(ProcessPayrollAction::class)->execute(CarbonImmutable::parse('2026-01-15', 'America/Tegucigalpa'), $owner);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('payroll_obligations', ['status' => 'pending', 'processing_attempts' => 1]);
        $this->assertNotNull(PayrollObligation::query()->firstOrFail()->processing_error);
    }

    public function test_payroll_and_templates_are_restructured_in_navigation_and_routes(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->get('/payroll')->assertRedirect('/expenses');
        $this->get('/expenses')->assertInertia(fn (Assert $page) => $page->component('Expenses/Index')->missing('payroll_summary')->missing('payroll_obligations'));
        $this->get('/expenses/templates')->assertNotFound();

        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $form = file_get_contents(resource_path('js/Components/Expenses/ExpenseFormDialog.vue'));
        $earnings = file_get_contents(resource_path('js/Pages/Earnings/Index.vue'));
        $this->assertStringNotContainsString('title="Nómina"', $layout);
        $this->assertStringNotContainsString('Plantilla rápida', $form);
        $this->assertStringNotContainsString('Nómina pagada por empleado', $earnings);
    }

    public function test_financial_demo_is_idempotent_and_uses_real_sales_and_payroll_logic(): void
    {
        $this->user('owner');
        Service::query()->create(['name' => 'Servicio demo', 'duration_minutes' => 60, 'price' => '780.00', 'is_active' => true]);

        $this->artisan('studio:generate-financial-demo', ['--months' => 2, '--sales' => 20, '--force' => true])->assertSuccessful();
        $this->assertSame(20, Sale::query()->where('client_name', 'like', 'Demo financiero %')->count());
        $this->assertSame(20, SalePayment::query()->whereHas('sale', fn ($query) => $query->where('client_name', 'like', 'Demo financiero %'))->count());
        $this->assertSame(3, SalePayment::query()->whereHas('sale', fn ($query) => $query->where('client_name', 'like', 'Demo financiero %'))->distinct()->count('method'));
        $employee = User::query()->where('email', 'employee.financial.demo@studio-lemus.local')->firstOrFail();
        $this->assertSame(4, PayrollObligation::query()->where('user_id', $employee->id)->where('status', 'paid')->count());
        $this->assertSame(4, Expense::query()->where('employee_id', $employee->id)->whereHas('payrollObligation')->count());

        $this->artisan('studio:generate-financial-demo', ['--months' => 2, '--sales' => 20, '--force' => true])->assertSuccessful();
        $this->assertSame(20, Sale::query()->where('client_name', 'like', 'Demo financiero %')->count());
        $this->assertSame(4, PayrollObligation::query()->where('user_id', $employee->id)->count());
    }

    public function test_financial_demo_rejects_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $exit = Artisan::call('studio:generate-financial-demo', ['--force' => true]);
        $this->assertSame(1, $exit);
        $this->assertDatabaseCount('sales', 0);
    }

    private function laborData(): array
    {
        return [
            'has_employment_profile' => true, 'monthly_salary' => '15000.00', 'contract_start_date' => '2026-01-01',
            'contract_end_date' => null, 'is_indefinite' => true, 'default_payment_method' => 'transfer',
            'auto_generate_payroll_expense' => true,
        ];
    }

    private function profileData(): array
    {
        return [
            'monthly_salary' => '15000.00', 'effective_from' => '2026-01-01', 'effective_to' => null,
            'contract_start_date' => '2026-01-01', 'contract_end_date' => null, 'is_indefinite' => true,
            'default_payment_method' => 'transfer', 'auto_generate_payroll_expense' => true,
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
