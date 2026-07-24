<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4DAppointmentCheckoutMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_historical_sales_and_items_are_backfilled_from_persisted_snapshots(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = User::factory()->create(['is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Histórico', 'duration_minutes' => 45, 'price' => '125.00', 'is_active' => true,
        ]);
        $payments = require database_path('migrations/2026_07_24_110200_create_sale_payments_table.php');
        $items = require database_path('migrations/2026_07_24_110100_add_appointment_checkout_fields_to_sale_items_table.php');
        $appointment = require database_path('migrations/2026_07_24_110000_add_appointment_id_to_sales_table.php');
        $payments->down();
        $items->down();
        $appointment->down();

        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SL-000001',
            'sold_by' => $employee->id,
            'sold_at' => now('UTC'),
            'subtotal' => '125.00',
            'total' => '125.00',
            'total_services' => 1,
            'status' => 'completed',
            'payment_method' => 'card',
            'card_fee_rate' => '3.25',
            'card_fee_amount' => '4.06',
            'net_amount' => '120.94',
            'checkout_token' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'service_id' => $service->id,
            'service_name' => 'Snapshot histórico',
            'duration_minutes' => 45,
            'unit_price' => '125.00',
            'quantity' => 1,
            'line_total' => '125.00',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $appointment->up();
        $items->up();
        $payments->up();

        $item = DB::table('sale_items')->where('sale_id', $saleId)->first();
        $payment = DB::table('sale_payments')->where('sale_id', $saleId)->first();
        $this->assertSame($employee->id, $item->performed_by);
        $this->assertSame(1, $item->position);
        $this->assertSame('0', (string) $item->allocated_card_fee_amount);
        $this->assertSame((string) $item->line_total, (string) $item->net_line_amount);
        $this->assertSame('final_payment', $payment->type);
        $this->assertSame('card', $payment->method);
        $this->assertSame('3.25', (string) $payment->card_fee_rate);
        $this->assertSame('4.06', (string) $payment->card_fee_amount);
        $this->assertSame('120.94', (string) $payment->net_amount);
        $this->assertTrue(Schema::hasColumn('sales', 'appointment_id'));
        $this->assertSame(1, DB::table('sale_payments')->where('sale_id', $saleId)->count());
        $performedBy = collect(Schema::getColumns('sale_items'))->firstWhere('name', 'performed_by');
        $this->assertTrue($performedBy['nullable'], 'sale_items.performed_by debe permanecer nullable.');
    }
}
