<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase3ASaleTest extends TestCase
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

    public function test_guest_cannot_open_or_create_a_sale(): void
    {
        $this->get('/sales/new')->assertRedirect(route('login'));
        $this->post('/sales')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_access_sales(): void
    {
        $employee = $this->user('employee', ['is_active' => false]);

        $this->actingAs($employee)->get('/sales/new')->assertRedirect(route('login'));
    }

    public function test_user_without_sales_access_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/sales/new')->assertForbidden();
        $this->post('/sales')->assertForbidden();
    }

    public function test_employee_can_open_new_sale_without_home_cash_or_configuration_navigation(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/sales/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Create')
                ->where('auth.navigation.home', false)
                ->where('auth.navigation.sales', true)
                ->missing('auth.navigation.cash'));

        $this->get('/configuration')->assertForbidden();
    }

    public function test_new_sale_receives_only_active_services(): void
    {
        $employee = $this->user('employee');
        $active = $this->service(['name' => 'Manicura activa']);
        $this->service(['name' => 'Servicio inactivo', 'is_active' => false]);

        $this->actingAs($employee)->get('/sales/new')
            ->assertInertia(fn (Assert $page) => $page
                ->has('services', 1)
                ->where('services.0.id', $active->id)
                ->where('services.0.name', 'Manicura activa')
                ->missing('services.0.is_active'));
    }

    public function test_one_or_multiple_services_can_be_sold(): void
    {
        $employee = $this->user('employee');
        $first = $this->service(['price' => '100.00']);
        $second = $this->service(['name' => 'Pedicura', 'price' => '200.00']);

        $response = $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $first->id, 'quantity' => 1],
            ['service_id' => $second->id, 'quantity' => 2],
        ]));

        $sale = Sale::query()->firstOrFail();
        $response->assertStatus(303)->assertRedirect(route('sales.receipt', $sale));
        $this->assertCount(2, $sale->items);
    }

    public function test_repeated_services_are_consolidated(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '25.00']);

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $service->id, 'quantity' => 2],
            ['service_id' => $service->id, 'quantity' => 3],
        ]));

        $this->assertDatabaseCount('sale_items', 1);
        $item = SaleItem::query()->firstOrFail();
        $this->assertSame(5, $item->quantity);
        $this->assertSame('125.00', $item->line_total);
    }

    public function test_aggregated_quantity_cannot_exceed_fifty(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $service->id, 'quantity' => 30],
            ['service_id' => $service->id, 'quantity' => 21],
        ]))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_zero_negative_and_excessive_quantities_are_rejected(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();

        foreach ([0, -1, 51] as $quantity) {
            $this->actingAs($employee)->post('/sales', $this->payload([
                ['service_id' => $service->id, 'quantity' => $quantity],
            ]))->assertSessionHasErrors('items.0.quantity');
        }

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_nonexistent_service_is_rejected(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => 999999, 'quantity' => 1],
        ]))->assertSessionHasErrors('items.0.service_id');
    }

    public function test_inactive_service_is_rejected_with_clear_error(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['name' => 'Gel retirado', 'is_active' => false]);

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $service->id, 'quantity' => 1],
        ]))->assertSessionHasErrors(['items' => 'Ya no están disponibles: Gel retirado.']);
    }

    public function test_frontend_money_and_identity_fields_are_ignored(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-19 18:15:30', 'UTC'));
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service(['price' => '145.75']);
        $payload = $this->payload([[
            'service_id' => $service->id,
            'quantity' => 2,
            'price' => '0.01',
            'line_total' => '0.02',
        ]]);
        $payload += [
            'subtotal' => '0.02',
            'total' => '0.02',
            'sold_by' => $other->id,
            'sold_at' => '2000-01-01 00:00:00',
            'sale_number' => 'FAKE-1',
        ];

        $this->actingAs($employee)->post('/sales', $payload);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('291.50', $sale->subtotal);
        $this->assertSame('291.50', $sale->total);
        $this->assertSame($employee->id, $sale->sold_by);
        $this->assertSame('2026-07-19 18:15:30', $sale->sold_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('SL-000001', $sale->sale_number);
    }

    public function test_backend_calculates_exact_decimal_totals_and_service_count(): void
    {
        $employee = $this->user('employee');
        $first = $this->service(['price' => '10.10']);
        $second = $this->service(['name' => 'Segundo', 'price' => '5.25']);

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $first->id, 'quantity' => 2],
            ['service_id' => $second->id, 'quantity' => 3],
        ]));

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('35.95', $sale->total);
        $this->assertSame(5, $sale->total_services);
    }

    public function test_decimal_calculation_avoids_binary_float_errors(): void
    {
        $employee = $this->user('employee');
        $first = $this->service(['price' => '0.10']);
        $second = $this->service(['name' => 'Segundo', 'price' => '0.20']);

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $first->id, 'quantity' => 3],
            ['service_id' => $second->id, 'quantity' => 1],
        ]));

        $this->assertSame('0.50', Sale::query()->firstOrFail()->total);
    }

    public function test_total_above_decimal_capacity_is_rejected(): void
    {
        $employee = $this->user('employee');
        $service = $this->service(['price' => '9999999999.99']);

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $service->id, 'quantity' => 2],
        ]))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_numbers_are_unique_and_immutable(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $action = app(CreateSaleAction::class);
        $first = $action->execute($employee, [['service_id' => $service->id, 'quantity' => 1]], (string) Str::uuid(), Sale::PAYMENT_METHOD_CASH);
        $second = $action->execute($employee, [['service_id' => $service->id, 'quantity' => 1]], (string) Str::uuid(), Sale::PAYMENT_METHOD_CASH);

        $this->assertSame('SL-000001', $first->sale_number);
        $this->assertSame('SL-000002', $second->sale_number);
        $this->assertNotSame($first->sale_number, $second->sale_number);

        $this->expectException(LogicException::class);
        $first->sale_number = 'SL-999999';
        $first->save();
    }

    public function test_sale_item_preserves_service_snapshots(): void
    {
        $employee = $this->user('employee');
        $service = $this->service([
            'name' => 'Acrílico premium',
            'description' => 'Aplicación completa',
            'duration_minutes' => 90,
            'price' => '450.00',
        ]);

        app(CreateSaleAction::class)->execute(
            $employee,
            [['service_id' => $service->id, 'quantity' => 2]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CASH,
        );

        $item = SaleItem::query()->firstOrFail();
        $this->assertSame('Acrílico premium', $item->service_name);
        $this->assertSame('Aplicación completa', $item->service_description);
        $this->assertSame(90, $item->duration_minutes);
        $this->assertSame('450.00', $item->unit_price);
        $this->assertSame('900.00', $item->line_total);
    }

    public function test_error_while_creating_items_rolls_back_entire_sale(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        SaleItem::creating(fn () => throw new RuntimeException('Fallo simulado de item'));

        try {
            app(CreateSaleAction::class)->execute(
                $employee,
                [['service_id' => $service->id, 'quantity' => 1]],
                (string) Str::uuid(),
                Sale::PAYMENT_METHOD_CASH,
            );
            $this->fail('La venta debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado de item', $exception->getMessage());
        } finally {
            SaleItem::flushEventListeners();
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_same_confirmation_token_does_not_duplicate_sale(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $token = (string) Str::uuid();
        $payload = $this->payload([['service_id' => $service->id, 'quantity' => 1]], $token);

        $this->actingAs($employee)->post('/sales', $payload)->assertStatus(303);
        $this->post('/sales', $payload)->assertStatus(303);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
    }

    public function test_confirmation_token_cannot_be_reused_for_different_cart(): void
    {
        $employee = $this->user('employee');
        $first = $this->service();
        $second = $this->service(['name' => 'Segundo']);
        $token = (string) Str::uuid();

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $first->id, 'quantity' => 1],
        ], $token));
        $this->post('/sales', $this->payload([
            ['service_id' => $second->id, 'quantity' => 1],
        ], $token))->assertSessionHasErrors('checkout_token');

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_employee_can_open_own_receipt_but_not_another_employees_receipt(): void
    {
        $seller = $this->user('employee');
        $other = $this->user('employee');
        $service = $this->service();
        $sale = app(CreateSaleAction::class)->execute(
            $seller,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CASH,
        );

        $this->actingAs($seller)->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Receipt')
                ->where('sale.sale_number', $sale->sale_number)
                ->missing('sale.checkout_token')
                ->missing('sale.request_hash'));

        $this->actingAs($other)->get(route('sales.receipt', $sale))->assertForbidden();
    }

    public function test_owner_can_open_any_receipt(): void
    {
        $seller = $this->user('employee');
        $owner = $this->user('owner');
        $service = $this->service();
        $sale = app(CreateSaleAction::class)->execute(
            $seller,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CASH,
        );

        $this->actingAs($owner)->get(route('sales.receipt', $sale))->assertOk();
    }

    public function test_receipt_uses_snapshots_after_service_changes_and_deletion(): void
    {
        $seller = $this->user('employee');
        $service = $this->service(['name' => 'Nombre original', 'price' => '300.00']);
        $sale = app(CreateSaleAction::class)->execute(
            $seller,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CASH,
        );

        $service->update(['name' => 'Nombre nuevo', 'price' => '999.00']);
        $service->delete();

        $this->assertNull($sale->items()->firstOrFail()->service_id);
        $this->actingAs($seller)->get(route('sales.receipt', $sale))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sale.items.0.service_name', 'Nombre original')
                ->where('sale.items.0.unit_price', '300.00')
                ->where('sale.total', '300.00'));
    }

    public function test_sales_have_no_cash_session_dependency_and_ignore_legacy_open_cash(): void
    {
        $employee = $this->user('employee');
        $service = $this->service();
        $cashSession = new CashSession;
        $cashSession->opened_by = $employee->id;
        $cashSession->opened_at = now('UTC');
        $cashSession->opening_amount = '100.00';
        $cashSession->status = CashSession::STATUS_OPEN;
        $cashSession->active_guard = CashSession::ACTIVE_GUARD_OPEN;
        $cashSession->save();

        $this->actingAs($employee)->post('/sales', $this->payload([
            ['service_id' => $service->id, 'quantity' => 1],
        ]))->assertStatus(303);

        $this->assertFalse(Schema::hasColumn('sales', 'cash_session_id'));
        $this->assertSame(1, CashSession::query()->count());
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_sales_routes_include_the_audited_cancellation_extension(): void
    {
        $this->assertTrue(Route::has('sales.create'));
        $this->assertTrue(Route::has('sales.store'));
        $this->assertTrue(Route::has('sales.receipt'));
        $this->assertFalse(Route::has('sales.index'));
        $this->assertTrue(Route::has('sales.cancel'));
        $this->assertFalse(Route::has('cash.open'));
        $this->assertFalse(Route::has('cash.close'));
        $this->get('/sales')->assertStatus(405);
        $this->assertFalse(Schema::hasTable('payments'));
    }

    public function test_sales_seeders_are_idempotent_with_exact_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $salesPermissions = [
            Permissions::SALES_ACCESS,
            Permissions::SALES_CREATE,
            Permissions::SALES_VIEW_OWN,
            Permissions::SALES_REPRINT,
        ];

        $this->assertSame(4, Permission::query()->whereIn('name', $salesPermissions)->count());
        foreach (['owner', 'administrator', 'employee'] as $role) {
            $this->assertTrue(Role::findByName($role)->hasAllPermissions($salesPermissions));
        }
        $this->assertTrue(Role::findByName('owner')->hasPermissionTo(Permissions::SALES_CANCEL));
        $this->assertFalse(Role::findByName('administrator')->hasPermissionTo(Permissions::SALES_CANCEL));
        $this->assertFalse(Role::findByName('employee')->hasPermissionTo(Permissions::SALES_CANCEL));
        $this->assertSame(0, Permission::query()->whereIn('name', [
            'sales.view_all',
            'sales.apply_discount',
        ])->count());
        $this->assertSame([Permissions::REPORTS_SALES_VIEW], Permission::query()
            ->where('name', 'like', 'reports.%')
            ->pluck('name')
            ->all());
        $this->assertSame(0, Permission::query()->where('name', 'like', 'expenses.%')->count());
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
            'name' => 'Manicura',
            'description' => 'Cuidado y esmaltado',
            'duration_minutes' => 45,
            'price' => '250.00',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function payload(array $items, ?string $token = null): array
    {
        return [
            'checkout_token' => $token ?? (string) Str::uuid(),
            'payment_method' => Sale::PAYMENT_METHOD_CASH,
            'items' => $items,
        ];
    }
}
