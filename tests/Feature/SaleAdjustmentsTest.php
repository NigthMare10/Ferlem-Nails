<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SaleAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_persists_named_additional_charge_and_exposes_it_in_receipt_and_invoice(): void
    {
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->assignRole('administrator');
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);

        $this->actingAs($administrator)->post('/sales', [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'card',
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
            'additional_charges' => [['name' => 'Diseño', 'amount' => '100.00']],
        ])->assertStatus(303);

        $sale = Sale::query()->with(['additionalCharges', 'payments'])->sole();
        $this->assertSame('200.00', $sale->subtotal);
        $this->assertSame('200.00', $sale->total);
        $this->assertSame('8.00', $sale->card_fee_amount);
        $this->assertSame('192.00', $sale->net_amount);
        $this->assertSame('Diseño', $sale->additionalCharges->sole()->name);
        $this->assertSame('Diseño', $sale->additionalCharges->sole()->description);

        $this->get(route('sales.receipt', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('sale.additional_charges.0.name', 'Diseño')
            ->where('sale.additional_charges.0.amount', '100.00')
            ->missing('sale.additional_charges.0.description'));
        $this->get(route('invoices.show', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('invoice.additional_charges.0.name', 'Diseño')
            ->where('invoice.additional_charges.0.amount', '100.00')
            ->missing('invoice.additional_charges.0.description'));
    }

    public function test_legacy_description_is_normalized_to_name(): void
    {
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->assignRole('administrator');
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);

        $this->actingAs($administrator)->post('/sales', [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
            'additional_charges' => [['description' => 'Diseño anterior', 'amount' => '25.00']],
        ])->assertStatus(303);

        $this->assertSame('Diseño anterior', Sale::query()->sole()->additionalCharges()->sole()->name);
    }

    public function test_additional_charge_requires_a_name_and_positive_amount(): void
    {
        $administrator = User::factory()->create(['is_active' => true]);
        $administrator->assignRole('administrator');
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);
        $payload = [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ];

        $this->actingAs($administrator)->post('/sales', [...$payload, 'additional_charges' => [['name' => '', 'amount' => '10.00']]])
            ->assertSessionHasErrors('additional_charges.0.name');
        $this->post('/sales', [...$payload, 'checkout_token' => (string) Str::uuid(), 'additional_charges' => [['name' => 'Diseño', 'amount' => '0']]])
            ->assertSessionHasErrors('additional_charges');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_employee_cannot_submit_a_discount(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        $employee->assignRole('employee');
        $service = Service::query()->create(['name' => 'Pedicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);

        $this->actingAs($employee)->post('/sales', [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
            'discount_percent' => '10.00',
        ])->assertSessionHasErrors('discount_percent');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_missing_discount_permission_disables_control_without_breaking_new_sale(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        Permission::query()->where('name', 'sales.apply_frequent_discount')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Log::spy();

        $this->actingAs($owner)->get('/sales/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Create')
                ->where('canApplyDiscount', false));

        Log::shouldHaveReceived('warning')
            ->with('Discount permission is missing; discount controls were disabled.')
            ->once();
    }
}
