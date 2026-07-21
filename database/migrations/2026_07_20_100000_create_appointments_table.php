<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 120);
            $table->string('client_phone', 30)->nullable();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->decimal('expected_total', 12, 2);
            $table->unsignedInteger('expected_duration_minutes');
            $table->enum('status', ['scheduled', 'completed', 'canceled', 'no_show'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->foreignId('no_show_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['assigned_to', 'status', 'scheduled_start'], 'appointments_assignee_status_start_index');
            $table->index(['status', 'scheduled_start'], 'appointments_status_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
