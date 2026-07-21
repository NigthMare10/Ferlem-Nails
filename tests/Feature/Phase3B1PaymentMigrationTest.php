<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase3B1PaymentMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_sales_are_backfilled_as_cash_without_totals_or_permanent_defaults(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = User::factory()->create(['is_active' => true]);
        $employee->assignRole('employee');
        $service = Service::query()->create([
            'name' => 'Servicio histórico',
            'description' => null,
            'duration_minutes' => 45,
            'price' => '780.00',
            'is_active' => true,
        ]);
        $sale = app(CreateSaleAction::class)->execute(
            $employee,
            [['service_id' => $service->id, 'quantity' => 1]],
            (string) Str::uuid(),
            Sale::PAYMENT_METHOD_CARD,
        );
        $migration = require database_path('migrations/2026_07_19_140000_add_payment_fields_to_sales_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('sales', 'payment_method'));
        $this->assertSame('780', (string) DB::table('sales')->where('id', $sale->id)->value('total'));

        $migration->up();

        $historical = DB::table('sales')->where('id', $sale->id)->first();
        $this->assertSame('cash', $historical->payment_method);
        $this->assertSame('0', (string) $historical->card_fee_rate);
        $this->assertSame('0', (string) $historical->card_fee_amount);
        $this->assertSame((string) $historical->total, (string) $historical->net_amount);

        $columns = collect(DB::select("PRAGMA table_info('sales')"))->keyBy('name');
        foreach (['payment_method', 'card_fee_rate', 'card_fee_amount', 'net_amount'] as $column) {
            $this->assertSame(1, $columns[$column]->notnull);
            $this->assertNull($columns[$column]->dflt_value);
        }

        $this->assertTrue(Schema::hasColumn('sale_items', 'sale_id'));
        $this->assertDatabaseCount('sale_items', 1);
    }
}
