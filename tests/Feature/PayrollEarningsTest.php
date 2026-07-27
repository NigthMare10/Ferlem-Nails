<?php

namespace Tests\Feature;

use App\Models\EmployeeCompensationProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollObligation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_paid_payroll_is_grouped_by_employee_for_month_and_updates_available_result(): void
    {
        $owner = $this->user('owner');
        $melany = $this->user('employee', 'Melany Lemus');
        $ana = $this->user('employee', 'Ana López');
        $pendingEmployee = $this->user('employee', 'Pendiente');
        $this->payroll($owner, $melany, '2026-07-15', '100.00', Expense::STATUS_RECORDED, PayrollObligation::STATUS_PAID, 'first');
        $this->payroll($owner, $melany, '2026-07-31', '150.00', Expense::STATUS_RECORDED, PayrollObligation::STATUS_PAID, 'second');
        $this->payroll($owner, $ana, '2026-07-15', '500.00', Expense::STATUS_RECORDED, PayrollObligation::STATUS_PAID, 'first');
        $this->payroll($owner, $ana, '2026-07-20', '900.00', Expense::STATUS_CANCELED, PayrollObligation::STATUS_PAID, 'second');
        $this->pending($owner, $pendingEmployee);

        $this->actingAs($owner)->get('/earnings?period=month&month=2026-07&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->missing('payroll_paid_by_employee')
            ->missing('payroll_pending')
            ->where('actual.paid_expenses', '750.00')
            ->where('actual.available_result', '-750.00'));
    }

    public function test_custom_period_empty_state_and_privacy_do_not_expose_payroll_props(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee', 'Empleada protegida');
        $this->payroll($owner, $employee, '2026-07-15', '6000.00', Expense::STATUS_RECORDED, PayrollObligation::STATUS_PAID, 'first');
        $this->payroll($owner, $employee, '2026-07-31', '6000.00', Expense::STATUS_RECORDED, PayrollObligation::STATUS_PAID, 'second');

        $this->actingAs($owner)->get('/earnings?period=custom&date_from=2026-07-15&date_to=2026-07-15&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->missing('payroll_paid_by_employee')
            ->where('actual.paid_expenses', '6000.00'));
        $this->get('/earnings?period=month&month=2026-08&mode=actual')->assertInertia(fn (Assert $page) => $page->missing('payroll_paid_by_employee'));
        $this->actingAs($administrator)->get('/earnings?period=month&month=2026-07&mode=actual')->assertInertia(fn (Assert $page) => $page
            ->missing('payroll_paid_by_employee')
            ->missing('payroll_pending')
            ->where('expense_actual.paid_expenses', '0.00'));
    }

    private function payroll(User $owner, User $employee, string $date, string $amount, string $expenseStatus, string $obligationStatus, string $installment): void
    {
        $profile = $this->profile($owner, $employee);
        $expense = new Expense;
        $expense->forceFill(['expense_number' => 'GA-'.str_pad((string) (Expense::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'checkout_token' => (string) Str::uuid(), 'request_hash' => hash('sha256', Str::uuid()->toString()), 'category_id' => ExpenseCategory::query()->where('slug', 'nomina')->value('id'), 'category_name_snapshot' => 'Nómina', 'expense_date' => $date, 'description' => 'Pago de nómina', 'amount' => $amount, 'payment_method' => 'cash', 'employee_id' => $employee->id, 'status' => $expenseStatus, 'recorded_by' => $owner->id]);
        $expense->save();
        $obligation = new PayrollObligation;
        $obligation->forceFill(['obligation_number' => 'NO-'.str_pad((string) (PayrollObligation::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'user_id' => $employee->id, 'compensation_profile_id' => $profile->id, 'period_year' => 2026, 'period_month' => 7, 'installment' => $installment, 'scheduled_date' => $date, 'amount' => $amount, 'status' => $obligationStatus, 'generated_at' => now(), 'paid_at' => $obligationStatus === PayrollObligation::STATUS_PAID ? now() : null, 'paid_by' => $obligationStatus === PayrollObligation::STATUS_PAID ? $owner->id : null, 'expense_id' => $expense->id]);
        $obligation->save();
    }

    private function pending(User $owner, User $employee): void
    {
        $obligation = new PayrollObligation;
        $obligation->forceFill(['obligation_number' => 'NO-PENDING', 'user_id' => $employee->id, 'compensation_profile_id' => $this->profile($owner, $employee)->id, 'period_year' => 2026, 'period_month' => 7, 'installment' => 'second', 'scheduled_date' => '2026-07-31', 'amount' => '700.00', 'status' => PayrollObligation::STATUS_PENDING, 'generated_at' => now()]);
        $obligation->save();
    }

    private function profile(User $owner, User $employee): EmployeeCompensationProfile
    {
        $existing = EmployeeCompensationProfile::query()->where('user_id', $employee->id)->first();
        if ($existing) {
            return $existing;
        }
        $profile = new EmployeeCompensationProfile;
        $profile->forceFill(['user_id' => $employee->id, 'monthly_salary' => '12000.00', 'first_payment_day' => 15, 'second_payment_rule' => 'last_day_of_month', 'effective_from' => '2026-07-01', 'is_active' => true, 'configured_by' => $owner->id]);
        $profile->save();

        return $profile;
    }

    private function user(string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['is_active' => true, ...($name ? ['name' => $name] : [])]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
