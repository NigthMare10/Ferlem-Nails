<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceClientSnapshotMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_backfills_linked_sales_and_leaves_direct_sales_null(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $migration = require database_path('migrations/2026_07_25_150000_add_client_name_to_sales_table.php');
        $migration->down();
        $appointmentId = DB::table('appointments')->insertGetId([
            'client_name' => 'Clienta histórica',
            'assigned_to' => $user->id,
            'scheduled_start' => now('UTC'),
            'scheduled_end' => now('UTC')->addHour(),
            'expected_total' => '100.00',
            'expected_duration_minutes' => 60,
            'status' => 'completed',
            'created_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        $linked = $this->insertSale($user, $appointmentId);
        $direct = $this->insertSale($user, null);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('sales', 'client_name'));
        $this->assertSame('Clienta histórica', DB::table('sales')->where('id', $linked)->value('client_name'));
        $this->assertNull(DB::table('sales')->where('id', $direct)->value('client_name'));
    }

    private function insertSale(User $user, ?int $appointmentId): int
    {
        return DB::table('sales')->insertGetId([
            'sale_number' => null,
            'appointment_id' => $appointmentId,
            'sold_by' => $user->id,
            'sold_at' => now('UTC'),
            'subtotal' => '100.00',
            'total' => '100.00',
            'total_services' => 1,
            'status' => 'completed',
            'payment_method' => 'cash',
            'card_fee_rate' => '0.00',
            'card_fee_amount' => '0.00',
            'net_amount' => '100.00',
            'checkout_token' => (string) Str::uuid(),
            'request_hash' => hash('sha256', (string) Str::uuid()),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
