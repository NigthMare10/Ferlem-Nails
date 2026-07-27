<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEvent;
use App\Models\User;
use App\Support\ExpenseAttachmentStorage;
use App\Support\Permissions;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase6AExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(ExpenseAttachmentStorage::DISK);
        Carbon::setTestNow('2026-07-27 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_and_employee_are_blocked_while_owner_and_administrator_access_expenses(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
        $this->actingAs($this->user('employee'))->get('/expenses')->assertForbidden();
        $this->actingAs($this->user('owner'))->get('/expenses')->assertInertia(fn (Assert $page) => $page->component('Expenses/Index'));
        $this->actingAs($this->user('administrator'))->get('/expenses')->assertInertia(fn (Assert $page) => $page->component('Expenses/Index'));
    }

    public function test_seeders_are_idempotent_with_exact_categories_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([
            'alimentacion', 'alquiler', 'mantenimiento', 'materiales-e-implementos',
            'nomina', 'otros', 'servicios-publicos', 'transporte',
        ], ExpenseCategory::query()->orderBy('slug')->pluck('slug')->all());
        $permissions = [
            Permissions::EXPENSES_ACCESS, Permissions::EXPENSES_VIEW, Permissions::EXPENSES_CREATE,
            Permissions::EXPENSES_UPDATE, Permissions::EXPENSES_CANCEL, Permissions::EXPENSES_VIEW_ATTACHMENT,
            Permissions::EXPENSES_MANAGE_CATEGORIES, Permissions::REPORTS_EXPENSES_VIEW,
        ];
        $this->assertSame(8, Permission::query()->whereIn('name', $permissions)->count());
        $this->assertTrue(Role::findByName('owner')->hasAllPermissions($permissions));
        $this->assertTrue(Role::findByName('administrator')->hasAllPermissions(array_diff($permissions, [Permissions::EXPENSES_MANAGE_CATEGORIES])));
        $this->assertFalse(Role::findByName('administrator')->hasPermissionTo(Permissions::EXPENSES_MANAGE_CATEGORIES));
        $this->assertFalse(Role::findByName('employee')->hasAnyPermission($permissions));
    }

    public function test_owner_creates_each_payment_method_with_server_owned_fields_and_readable_number(): void
    {
        $owner = $this->user('owner');
        foreach ([Expense::PAYMENT_METHOD_CASH, Expense::PAYMENT_METHOD_CARD, Expense::PAYMENT_METHOD_TRANSFER] as $method) {
            $this->actingAs($owner)->post('/expenses', [
                ...$this->payload(['payment_method' => $method]),
                'recorded_by' => 999999,
                'status' => Expense::STATUS_CANCELED,
                'expense_number' => 'MANIPULADO',
            ])->assertRedirect();
        }

        $this->assertSame(3, Expense::query()->count());
        foreach (Expense::query()->orderBy('id')->get() as $index => $expense) {
            $this->assertSame('GA-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT), $expense->expense_number);
            $this->assertSame($owner->id, $expense->recorded_by);
            $this->assertSame(Expense::STATUS_RECORDED, $expense->status);
            $this->assertSame(1, $expense->events()->where('type', ExpenseEvent::TYPE_CREATED)->count());
        }
    }

    public function test_amount_category_employee_and_date_validation_enforce_domain_rules(): void
    {
        $owner = $this->user('owner');
        $inactiveCategory = ExpenseCategory::query()->where('slug', 'otros')->firstOrFail();
        $inactiveCategory->update(['is_active' => false]);
        $inactiveEmployee = $this->user('employee', ['is_active' => false]);

        foreach (['0', '-1', '1.001'] as $amount) {
            $this->actingAs($owner)->from('/expenses')->post('/expenses', $this->payload(['amount' => $amount]))
                ->assertSessionHasErrors('amount');
        }
        $this->post('/expenses', $this->payload(['category_id' => $inactiveCategory->id]))->assertSessionHasErrors('category_id');
        $this->post('/expenses', $this->payload(['employee_id' => $inactiveEmployee->id]))->assertSessionHasErrors('employee_id');
        $this->post('/expenses', $this->payload(['expense_date' => '2026-07-28']))->assertSessionHasErrors('expense_date');
        $this->post('/expenses', $this->payload(['expense_date' => '2026-06-01']))->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_checkout_token_is_idempotent_and_rejects_different_payload(): void
    {
        $owner = $this->user('owner');
        $token = (string) Str::uuid();
        $payload = $this->payload(['checkout_token' => $token]);
        $this->actingAs($owner)->post('/expenses', $payload)->assertRedirect();
        $this->post('/expenses', $payload)->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);
        $this->post('/expenses', [...$payload, 'description' => 'Otro gasto'])->assertSessionHasErrors('checkout_token');
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_private_image_and_pdf_are_stored_streamed_and_kept_after_cancellation(): void
    {
        $owner = $this->user('owner');
        foreach ([
            UploadedFile::fake()->image('comprobante.jpg'),
            UploadedFile::fake()->image('comprobante.png'),
            UploadedFile::fake()->image('comprobante.webp'),
            UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ] as $attachment) {
            $this->actingAs($owner)->post('/expenses', $this->payload(['attachment' => $attachment]))->assertRedirect();
        }
        $expense = Expense::query()->firstOrFail();
        Storage::disk(ExpenseAttachmentStorage::DISK)->assertExists($expense->attachment_path);
        Storage::disk('public')->assertMissing($expense->attachment_path);
        $this->get(route('expenses.attachment', $expense))->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->actingAs($this->user('employee'))->get(route('expenses.attachment', $expense))->assertForbidden();
        $this->actingAs($owner)->post(route('expenses.cancel', $expense), ['cancellation_reason' => 'Registro duplicado.'])->assertStatus(303);
        Storage::disk(ExpenseAttachmentStorage::DISK)->assertExists($expense->attachment_path);
    }

    public function test_invalid_and_oversized_attachments_are_rejected_and_rollback_removes_file(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->post('/expenses', $this->payload([
            'attachment' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('attachment');
        $this->post('/expenses', $this->payload([
            'attachment' => UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf'),
        ]))->assertSessionHasErrors('attachment');

        Event::listen('eloquent.creating: '.ExpenseEvent::class, fn () => throw new RuntimeException('forced rollback'));
        try {
            $this->post('/expenses', $this->payload(['attachment' => UploadedFile::fake()->image('rollback.jpg')]));
        } finally {
            Event::forget('eloquent.creating: '.ExpenseEvent::class);
        }
        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame([], Storage::disk(ExpenseAttachmentStorage::DISK)->allFiles());
    }

    public function test_recorded_expense_can_be_edited_with_readable_event_but_canceled_expense_cannot(): void
    {
        $owner = $this->user('owner');
        $expense = $this->createExpense($owner);
        $transport = ExpenseCategory::query()->where('slug', 'transporte')->firstOrFail();

        $this->actingAs($owner)->put(route('expenses.update', $expense), [
            'expense_date' => '2026-07-26', 'category_id' => $transport->id,
            'description' => 'Transporte de materiales', 'amount' => '250.50',
            'payment_method' => 'transfer', 'vendor' => 'Conductor', 'employee_id' => null,
        ])->assertStatus(303);
        $expense->refresh();
        $this->assertSame('250.50', $expense->amount);
        $event = $expense->events()->where('type', ExpenseEvent::TYPE_UPDATED)->firstOrFail();
        $this->assertSame('Materiales e implementos', $event->previous_values['category']);
        $this->assertSame('Transporte', $event->new_values['category']);

        $this->post(route('expenses.cancel', $expense), ['cancellation_reason' => 'Se registró por error.'])->assertStatus(303);
        $this->put(route('expenses.update', $expense), [
            'expense_date' => '2026-07-26', 'category_id' => $transport->id, 'description' => 'Cambio inválido',
            'amount' => '1.00', 'payment_method' => 'cash', 'vendor' => null, 'employee_id' => null,
        ])->assertSessionHasErrors('expense');
        $this->expectException(LogicException::class);
        $expense->fresh()->delete();
    }

    public function test_edit_preserves_unchanged_inactive_references_and_audits_same_named_employee_changes(): void
    {
        $owner = $this->user('owner');
        $first = $this->user('employee', ['name' => 'Ana Empleada']);
        $second = $this->user('employee', ['name' => 'Ana Empleada']);
        $expense = $this->createExpense($owner, ['employee_id' => $first->id]);
        $category = $expense->category;
        $category->update(['is_active' => false]);
        $first->update(['is_active' => false]);

        $payload = [
            'expense_date' => '2026-07-27', 'category_id' => $category->id,
            'description' => 'Compra de materiales', 'amount' => '125.50',
            'payment_method' => 'cash', 'vendor' => 'Proveedor local', 'employee_id' => $first->id,
        ];
        $this->actingAs($owner)->put(route('expenses.update', $expense), $payload)->assertStatus(303);
        $this->put(route('expenses.update', $expense), [...$payload, 'category_id' => ExpenseCategory::query()->where('slug', 'transporte')->value('id'), 'employee_id' => $second->id])->assertStatus(303);

        $event = $expense->events()->where('type', ExpenseEvent::TYPE_UPDATED)->latest('id')->firstOrFail();
        $this->assertSame($first->id, $event->previous_values['employee']['id']);
        $this->assertSame($second->id, $event->new_values['employee']['id']);
    }

    public function test_cancellation_is_single_audited_transition_and_remains_in_history(): void
    {
        $owner = $this->user('owner');
        $expense = $this->createExpense($owner);
        $this->actingAs($owner)->post(route('expenses.cancel', $expense), ['cancellation_reason' => 'Compra registrada dos veces.'])->assertStatus(303);
        $expense->refresh();
        $this->assertSame(Expense::STATUS_CANCELED, $expense->status);
        $this->assertSame($owner->id, $expense->canceled_by);
        $this->assertSame(1, $expense->events()->where('type', ExpenseEvent::TYPE_CANCELED)->count());
        $this->post(route('expenses.cancel', $expense), ['cancellation_reason' => 'Segundo intento.'])->assertSessionHasErrors('expense');
        $this->get('/expenses?status=canceled')->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.expense_number', $expense->expense_number));
    }

    public function test_combined_filters_pagination_and_order_are_backend_driven(): void
    {
        $owner = $this->user('owner');
        $category = ExpenseCategory::query()->where('slug', 'materiales-e-implementos')->firstOrFail();
        for ($i = 0; $i < 21; $i++) {
            $this->createExpense($owner, ['description' => "Material {$i}", 'amount' => '10.00']);
        }
        $this->actingAs($owner)->get('/expenses?search=Material&date_from=2026-07-27&date_to=2026-07-27&category_id='.$category->id.'&status=recorded&payment_method=cash&recorded_by='.$owner->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenses.data', 20)
                ->where('expenses.meta.total', 21)
                ->where('filters.search', 'Material'));
    }

    public function test_owner_manages_categories_without_exposing_slug_or_delete_route(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $this->actingAs($administrator)->get('/expenses/categories')->assertForbidden();
        $this->actingAs($owner)->post('/expenses/categories', ['name' => 'Papelería'])->assertStatus(303);
        $category = ExpenseCategory::query()->where('name', 'Papelería')->firstOrFail();
        $this->patch("/expenses/categories/{$category->id}/status", ['is_active' => false])->assertStatus(303);
        $this->assertFalse($category->fresh()->is_active);
        $this->get('/expenses/categories')->assertInertia(fn (Assert $page) => $page
            ->component('Expenses/Categories')
            ->missing('categories.0.slug'));
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => in_array('DELETE', $route->methods(), true) && str_contains($route->uri(), 'expenses')));
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function createExpense(User $user, array $overrides = []): Expense
    {
        $this->actingAs($user)->post('/expenses', $this->payload($overrides))->assertRedirect();

        return Expense::query()->latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        $category = ExpenseCategory::query()->where('slug', 'materiales-e-implementos')->firstOrFail();

        return [
            'checkout_token' => (string) Str::uuid(),
            'expense_date' => '2026-07-27',
            'category_id' => $category->id,
            'description' => 'Compra de materiales',
            'amount' => '125.50',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
            'vendor' => 'Proveedor local',
            'employee_id' => null,
            'notes' => 'Entrega completa',
            ...$overrides,
        ];
    }
}
