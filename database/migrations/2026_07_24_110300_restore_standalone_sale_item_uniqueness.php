<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('sale_items'))->pluck('name');
        if ($indexes->contains('sale_items_sale_service_unique')) {
            return;
        }

        $duplicates = DB::table('sale_items')
            ->select('sale_id', 'service_id')
            ->whereNotNull('service_id')
            ->groupBy('sale_id', 'service_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicates) {
            throw new RuntimeException('No se puede restaurar la unicidad de servicios sin revisar líneas duplicadas.');
        }

        Schema::table('sale_items', fn (Blueprint $table) => $table->unique(
            ['sale_id', 'service_id'],
            'sale_items_sale_service_unique',
        ));
    }

    public function down(): void
    {
        // The index belongs to the original sales schema and must survive rollback of appointment checkout.
    }
};
