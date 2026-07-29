<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_frequent_client')->default(false)->after('subtotal');
            $table->decimal('subtotal_before_discount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal_before_discount');
        });
        Schema::table('sale_additional_charges', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('description');
        });
        DB::table('sales')->update(['subtotal_before_discount' => DB::raw('subtotal')]);
        DB::table('sale_additional_charges')->whereNull('name')->update(['name' => DB::raw('description')]);
    }

    public function down(): void
    {
        Schema::table('sale_additional_charges', fn (Blueprint $table) => $table->dropColumn('name'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['is_frequent_client', 'subtotal_before_discount', 'discount_percent']));
    }
};
