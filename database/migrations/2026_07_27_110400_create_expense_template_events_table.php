<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_template_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_template_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['expense_template_id', 'occurred_at'], 'expense_template_events_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_template_events');
    }
};
