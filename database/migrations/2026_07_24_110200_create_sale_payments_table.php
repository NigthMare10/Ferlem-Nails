<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->enum('type', ['deposit_applied', 'final_payment']);
            $table->enum('method', ['cash', 'card']);
            $table->decimal('amount', 12, 2);
            $table->decimal('card_fee_rate', 5, 2);
            $table->decimal('card_fee_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->foreignId('appointment_deposit_id')->nullable()->unique('sale_payments_deposit_unique')->constrained('appointment_deposits')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'type'], 'sale_payments_sale_type_index');
            $table->unique(['sale_id', 'type'], 'sale_payments_sale_type_unique');
            $table->index(['method', 'created_at'], 'sale_payments_method_created_index');
        });

        DB::table('sales')->orderBy('id')->chunkById(500, function ($sales): void {
            $now = now('UTC');
            $rows = $sales->map(fn ($sale) => [
                'sale_id' => $sale->id,
                'type' => 'final_payment',
                'method' => $sale->payment_method,
                'amount' => $sale->total,
                'card_fee_rate' => $sale->card_fee_rate,
                'card_fee_amount' => $sale->card_fee_amount,
                'net_amount' => $sale->net_amount,
                'appointment_deposit_id' => null,
                'created_at' => $sale->created_at ?? $now,
                'updated_at' => $sale->updated_at ?? $now,
            ])->all();

            if ($rows !== []) {
                DB::table('sale_payments')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
