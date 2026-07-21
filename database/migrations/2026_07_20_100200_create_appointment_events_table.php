<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->string('type', 40);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'occurred_at'], 'appointment_events_appointment_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
    }
};
