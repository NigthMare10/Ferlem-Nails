<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_owner_can_cancel_a_completed_sale_without_deleting_its_financial_history(): void
    {
        $owner = $this->user('owner');
        $sale = $this->sale($owner);
        $itemCount = $sale->items()->count();
        $paymentCount = $sale->payments()->count();

        $this->actingAs($owner)->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => 'Cobro creado por error.'])
            ->assertStatus(303);

        $sale->refresh();
        $this->assertSame(Sale::STATUS_CANCELED, $sale->status);
        $this->assertNotNull($sale->canceled_at);
        $this->assertSame($owner->id, $sale->canceled_by);
        $this->assertSame('Cobro creado por error.', $sale->cancellation_reason);
        $this->assertSame($itemCount, $sale->items()->count());
        $this->assertSame($paymentCount, $sale->payments()->count());
    }

    public function test_cancel_requires_permission_reason_and_completed_state(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $sale = $this->sale($owner);

        $this->actingAs($employee)->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => 'No autorizado.'])->assertForbidden();
        $this->actingAs($owner)->from("/sales/{$sale->id}/receipt")
            ->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => ''])
            ->assertSessionHasErrors('cancellation_reason');
        $this->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => 'Cobro creado por error.'])->assertStatus(303);
        $this->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => 'Segundo intento.'])
            ->assertSessionHasErrors('sale');
    }

    public function test_canceled_sale_is_excluded_and_reported_as_secondary_data(): void
    {
        $owner = $this->user('owner');
        $sale = $this->sale($owner);
        $this->actingAs($owner)->post("/sales/{$sale->id}/cancel", ['cancellation_reason' => 'Cobro creado por error.']);

        $this->get('/earnings?period=today&date='.now('America/Tegucigalpa')->format('Y-m-d'))
            ->assertInertia(fn ($page) => $page
                ->where('actual.gross_revenue', '0.00')
                ->where('actual.canceled_sales_count', 1)
                ->where('actual.canceled_amount', $sale->total)
                ->where('payment_distribution.0.amount', '0.00')
                ->where('payment_distribution.1.amount', '0.00')
                ->where('payment_distribution.2.amount', '0.00'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function sale(User $user): Sale
    {
        $service = Service::query()->create([
            'name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true,
        ]);
        $this->actingAs($user)->post('/sales', [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ])->assertStatus(303);

        return Sale::query()->firstOrFail();
    }
}
