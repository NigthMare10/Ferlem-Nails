<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card']);
            $table->decimal('card_fee_rate', 5, 2);
            $table->decimal('card_fee_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->enum('status', ['pending', 'applied', 'refunded', 'partially_refunded', 'retained'])->default('pending');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('applied_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('retained_amount', 12, 2)->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at'], 'appointment_deposits_status_paid_index');
            $table->index(['recorded_by', 'paid_at'], 'appointment_deposits_recorder_paid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_deposits');
    }
};
