<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('sales', function (Blueprint $table) {
                $table->enum('payment_method', ['cash', 'card'])->nullable()->after('status');
                $table->decimal('card_fee_rate', 5, 2)->nullable()->after('payment_method');
                $table->decimal('card_fee_amount', 12, 2)->nullable()->after('card_fee_rate');
                $table->decimal('net_amount', 12, 2)->nullable()->after('card_fee_amount');
            });

            DB::table('sales')->whereNull('payment_method')->update([
                'payment_method' => 'cash',
                'card_fee_rate' => '0.00',
                'card_fee_amount' => '0.00',
                'net_amount' => DB::raw('total'),
            ]);

            Schema::table('sales', function (Blueprint $table) {
                $table->enum('payment_method', ['cash', 'card'])->nullable(false)->change();
                $table->decimal('card_fee_rate', 5, 2)->nullable(false)->change();
                $table->decimal('card_fee_amount', 12, 2)->nullable(false)->change();
                $table->decimal('net_amount', 12, 2)->nullable(false)->change();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['payment_method', 'card_fee_rate', 'card_fee_amount', 'net_amount']);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
