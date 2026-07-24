<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_deposit_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_deposit_id')->constrained('appointment_deposits')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('refunded_at');
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->uuid('operation_token')->unique();
            $table->timestamps();

            $table->index(['appointment_deposit_id', 'refunded_at'], 'deposit_refunds_deposit_time_index');
            $table->index(['refunded_by', 'refunded_at'], 'deposit_refunds_actor_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_deposit_refunds');
    }
};
