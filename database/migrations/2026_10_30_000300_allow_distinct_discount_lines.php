<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('sale_items', 'sale_items_sale_service_unique')) {
            Schema::table('sale_items', fn (Blueprint $t) => $t->dropUnique('sale_items_sale_service_unique'));
        }
        if (! Schema::hasIndex('sale_items', 'sale_items_sale_service_lookup')) {
            Schema::table('sale_items', fn (Blueprint $t) => $t->index(['sale_id', 'service_id'], 'sale_items_sale_service_lookup'));
        }
    }

    public function down(): void
    {
        if (DB::table('sale_items')->select('sale_id', 'service_id')->whereNotNull('service_id')->groupBy('sale_id', 'service_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback blocked: distinct historical discount lines must not be consolidated or deleted.');
        }
        if (Schema::hasIndex('sale_items', 'sale_items_sale_service_lookup')) {
            Schema::table('sale_items', fn (Blueprint $t) => $t->dropIndex('sale_items_sale_service_lookup'));
        }
        if (! Schema::hasIndex('sale_items', 'sale_items_sale_service_unique')) {
            Schema::table('sale_items', fn (Blueprint $t) => $t->unique(['sale_id', 'service_id'], 'sale_items_sale_service_unique'));
        }
    }
};
