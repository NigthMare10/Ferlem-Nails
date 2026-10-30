<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['created', 'updated', 'canceled']);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['expense_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_events');
    }
};
