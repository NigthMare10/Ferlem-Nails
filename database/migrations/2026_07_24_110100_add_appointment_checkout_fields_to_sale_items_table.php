<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('performed_by')->nullable()->after('service_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_item_id')->nullable()->after('performed_by')->constrained('appointment_items')->restrictOnDelete();
            $table->unsignedInteger('position')->nullable()->after('appointment_item_id');
            $table->decimal('allocated_card_fee_amount', 12, 2)->default(0)->after('line_total');
            $table->decimal('net_line_amount', 12, 2)->nullable()->after('allocated_card_fee_amount');
        });

        $indexes = collect(Schema::getIndexes('sale_items'))->pluck('name');
        Schema::table('sale_items', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('sale_items_sale_id_index')) {
                $table->index('sale_id', 'sale_items_sale_id_index');
            }
            if (! $indexes->contains('sale_items_service_id_index')) {
                $table->index('service_id', 'sale_items_service_id_index');
            }
        });

        $indexes = collect(Schema::getIndexes('sale_items'))->pluck('name');
        if ($indexes->contains('sale_items_sale_service_unique')) {
            Schema::table('sale_items', fn (Blueprint $table) => $table->dropUnique('sale_items_sale_service_unique'));
        }

        DB::table('sales')->orderBy('id')->chunkById(500, function ($sales): void {
            foreach ($sales as $sale) {
                DB::table('sale_items')->where('sale_id', $sale->id)->update([
                    'performed_by' => $sale->sold_by,
                    'net_line_amount' => DB::raw('line_total'),
                    'allocated_card_fee_amount' => '0.00',
                ]);
            }
        });

        DB::table('sale_items')->orderBy('sale_id')->orderBy('id')->get(['id', 'sale_id'])
            ->groupBy('sale_id')
            ->each(function ($items): void {
                foreach ($items->values() as $index => $item) {
                    DB::table('sale_items')->where('id', $item->id)->update(['position' => $index + 1]);
                }
            });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable(false)->change();
            $table->decimal('net_line_amount', 12, 2)->nullable(false)->change();
            $table->unique(['sale_id', 'position'], 'sale_items_sale_position_unique');
            $table->unique('appointment_item_id', 'sale_items_appointment_item_unique');
            $table->index(['performed_by', 'sale_id'], 'sale_items_performer_sale_index');
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('sale_items'))->pluck('name');
        Schema::table('sale_items', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('sale_items_sale_position_unique')) {
                $table->dropUnique('sale_items_sale_position_unique');
            }
            if ($indexes->contains('sale_items_appointment_item_unique')) {
                $table->dropUnique('sale_items_appointment_item_unique');
            }
            if ($indexes->contains('sale_items_performer_sale_index')) {
                $table->dropIndex('sale_items_performer_sale_index');
            }
            $table->dropConstrainedForeignId('appointment_item_id');
            $table->dropConstrainedForeignId('performed_by');
            $table->dropColumn(['position', 'allocated_card_fee_amount', 'net_line_amount']);
        });

        $duplicates = DB::table('sale_items')
            ->select('sale_id', 'service_id')
            ->whereNotNull('service_id')
            ->groupBy('sale_id', 'service_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if (! $duplicates) {
            Schema::table('sale_items', fn (Blueprint $table) => $table->unique(['sale_id', 'service_id'], 'sale_items_sale_service_unique'));
        }
    }
};
