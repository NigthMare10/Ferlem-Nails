<?php

namespace Tests\Feature;

use App\Actions\Expenses\BuildExpensesListAction;
use App\Actions\Payroll\CancelPayrollObligationAction;
use App\Actions\Payroll\ConfigureCompensationProfileAction;
use App\Actions\Payroll\GeneratePayrollObligationsAction;
use App\Actions\Payroll\MarkPayrollObligationPaidAction;
use App\Actions\Reports\BuildExpensesSummaryAction;
use App\Models\EmployeeCompensationProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InternalNotification;
use App\Models\PayrollEvent;
use App\Models\PayrollObligation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollAuditPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_without_payroll_permission_cannot_observe_payroll_expenses(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $expense = $this->payrollExpense($owner);

        $this->assertSame(0, app(BuildExpensesListAction::class)->execute($administrator, [])->total());
        $this->actingAs($administrator);
        $this->get(route('expenses.show', $expense))
            ->assertForbidden()
            ->assertHeaderMissing('X-Inertia')
            ->assertSee('Errors\/Forbidden', false)
            ->assertDontSee('6000.00')
            ->assertDontSee('Pago de nómina')
            ->assertDontSee($expense->employee->name);
        $this->get(route('expenses.attachment', $expense))->assertForbidden();
        $this->put(route('expenses.update', $expense), [])->assertForbidden();
        $this->post(route('expenses.cancel', $expense), [])->assertForbidden();

        $this->assertSame(1, app(BuildExpensesListAction::class)->execute($owner, [])->total());
        $filters = ['period' => 'custom', 'mode' => 'actual', 'date_from' => '2026-07-15', 'date_to' => '2026-07-15'];
        $this->assertSame('0.00', app(BuildExpensesSummaryAction::class)->execute($filters, $administrator)['expense_actual']['paid_expenses']);
        $this->assertSame('6000.00', app(BuildExpensesSummaryAction::class)->execute($filters, $owner)['expense_actual']['paid_expenses']);
        $this->actingAs($owner)->get(route('expenses.show', $expense))->assertOk();
    }

    public function test_salary_expense_denial_has_distinct_inertia_and_json_contracts(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $expense = $this->payrollExpense($owner);
        $url = route('expenses.show', $expense);

        $this->actingAs($administrator)->withHeaders($this->inertiaHeaders())->get($url)
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Errors/Forbidden')
            ->assertJsonMissing(['6000.00', 'Pago de nómina', $expense->employee->name]);

        $this->flushHeaders()->withHeaders(['Accept' => 'application/json'])->get($url)
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message'])
            ->assertJsonMissing(['6000.00', 'Pago de nómina', $expense->employee->name]);
    }

    public function test_notification_navigation_remains_inertia_and_polling_remains_json(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->withHeaders($this->inertiaHeaders())->get('/notifications')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Notifications/Index');
        $this->withHeaders(['Accept' => 'application/json'])->get('/notifications/recent')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['data' => ['unread_count', 'recent', 'as_of']]);
    }

    public function test_profile_creation_and_replacement_are_append_only_audited(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $action = app(ConfigureCompensationProfileAction::class);
        $first = $action->execute($owner, $employee, ['monthly_salary' => '12000.00', 'effective_from' => '2026-07-01']);
        $second = $action->execute($owner, $employee, ['monthly_salary' => '14000.00', 'effective_from' => '2026-08-01']);

        $this->assertSame('2026-07-31', $first->fresh()->effective_to->toDateString());
        $this->assertSame('12000.00', $first->fresh()->monthly_salary);
        $this->assertDatabaseHas('payroll_events', ['subject_type' => EmployeeCompensationProfile::class, 'subject_id' => $second->id, 'event_type' => 'profile.created']);
        $this->assertDatabaseHas('payroll_events', ['subject_type' => EmployeeCompensationProfile::class, 'subject_id' => $first->id, 'event_type' => 'profile.closed']);
        $this->expectException(\LogicException::class);
        PayrollEvent::query()->firstOrFail()->forceFill(['notes' => 'No permitido'])->save();
    }

    public function test_generation_creates_one_append_only_obligation_event_and_dry_run_creates_none(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, ['monthly_salary' => '12000.01', 'effective_from' => '2026-07-01']);
        $action = app(GeneratePayrollObligationsAction::class);
        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner, true);
        $this->assertDatabaseCount('payroll_obligations', 0);
        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner);
        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner);
        $this->assertDatabaseCount('payroll_obligations', 2);
        $this->assertDatabaseCount('payroll_events', 3);
        $this->assertSame('6000.00', PayrollObligation::query()->where('installment', 'first')->value('amount'));
        $this->assertSame('6000.01', PayrollObligation::query()->where('installment', 'second')->value('amount'));
    }

    public function test_paid_and_canceled_obligations_keep_complete_readable_audit(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, ['monthly_salary' => '12000.00', 'effective_from' => '2026-07-01']);
        app(GeneratePayrollObligationsAction::class)->execute(CarbonImmutable::parse('2026-07-01'), $owner);
        $first = PayrollObligation::query()->where('installment', 'first')->firstOrFail();
        $second = PayrollObligation::query()->where('installment', 'second')->firstOrFail();

        app(MarkPayrollObligationPaidAction::class)->execute($owner, $first, ['expense_date' => '2026-07-15', 'payment_method' => 'transfer']);
        app(CancelPayrollObligationAction::class)->execute($owner, $second, 'Pago no requerido por cierre acordado.');

        $paid = $first->events()->where('event_type', 'obligation.paid')->firstOrFail();
        $canceled = $second->events()->where('event_type', 'obligation.canceled')->firstOrFail();
        $this->assertSame($first->fresh()->expense->expense_number, $paid->new_values['expense_number']);
        $this->assertSame('transfer', $paid->new_values['payment_method']);
        $this->assertSame('Pago no requerido por cierre acordado.', $canceled->notes);

        $this->actingAs($owner)->get('/payroll')->assertRedirect('/expenses?section=payroll');
        $this->get('/expenses?section=payroll')->assertInertia(fn (Assert $page) => $page
            ->component('Expenses/Index')
            ->has('payroll_obligations', 2));
    }

    public function test_new_obligations_notify_once_after_commit_without_leaking_to_employee(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        app(ConfigureCompensationProfileAction::class)->execute($owner, $employee, ['monthly_salary' => '12000.00', 'effective_from' => '2026-07-01']);
        $action = app(GeneratePayrollObligationsAction::class);

        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner, true);
        $this->assertDatabaseCount('notifications', 0);
        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(0, InternalNotification::query()->where('notifiable_id', $employee->id)->count());
        $this->assertTrue(InternalNotification::query()->get()->every(fn (InternalNotification $notification) => str_starts_with($notification->dedupe_key, 'payroll-obligation-generated:')));
        $this->assertTrue(InternalNotification::query()->get()->every(fn (InternalNotification $notification) => $notification->data['url'] === '/expenses?section=payroll'));
        $action->execute(CarbonImmutable::parse('2026-07-01'), $owner);
        $this->assertDatabaseCount('notifications', 2);

        Event::listen('eloquent.creating: '.PayrollEvent::class, fn () => throw new RuntimeException('forced payroll rollback'));
        try {
            $action->execute(CarbonImmutable::parse('2026-08-01'), $owner);
            $this->fail('La generación debía revertirse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced payroll rollback', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.PayrollEvent::class);
        }
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('payroll_obligations', ['period_year' => 2026, 'period_month' => 8]);
    }

    private function payrollExpense(User $owner): Expense
    {
        $employee = $this->user('employee');
        $profile = (new EmployeeCompensationProfile)->forceFill(['user_id' => $employee->id, 'monthly_salary' => '12000.00', 'first_payment_day' => 15, 'second_payment_rule' => 'last_day_of_month', 'effective_from' => '2026-07-01', 'is_active' => true, 'configured_by' => $owner->id]);
        $profile->save();
        $expense = (new Expense)->forceFill(['expense_number' => 'GA-999999', 'checkout_token' => '00000000-0000-0000-0000-000000000001', 'request_hash' => str_repeat('a', 64), 'category_id' => ExpenseCategory::query()->where('slug', 'nomina')->value('id'), 'category_name_snapshot' => 'Nómina', 'expense_date' => '2026-07-15', 'description' => 'Pago de nómina', 'amount' => '6000.00', 'payment_method' => 'cash', 'employee_id' => $employee->id, 'status' => Expense::STATUS_RECORDED, 'recorded_by' => $owner->id]);
        $expense->save();
        $obligation = (new PayrollObligation)->forceFill(['obligation_number' => 'NO-999999', 'user_id' => $employee->id, 'compensation_profile_id' => $profile->id, 'period_year' => 2026, 'period_month' => 7, 'installment' => 'first', 'scheduled_date' => '2026-07-15', 'amount' => '6000.00', 'status' => 'paid', 'generated_at' => now(), 'expense_id' => $expense->id]);
        $obligation->save();

        return $expense;
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists(public_path('build/manifest.json'))
                ? hash_file('xxh128', public_path('build/manifest.json'))
                : '',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
