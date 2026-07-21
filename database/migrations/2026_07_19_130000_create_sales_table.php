<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 20)->nullable();
            $table->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('total_services');
            $table->enum('status', ['completed'])->default('completed');
            $table->uuid('checkout_token');
            $table->char('request_hash', 64);
            $table->timestamps();

            $table->unique('sale_number', 'sales_sale_number_unique');
            $table->unique('checkout_token', 'sales_checkout_token_unique');
            $table->index(['sold_by', 'sold_at'], 'sales_sold_by_sold_at_index');
            $table->index(['status', 'sold_at'], 'sales_status_sold_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
