<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_close_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->time('send_time')->default('21:00');
            $table->string('timezone', 64)->default('America/Tegucigalpa');
            $table->string('recipient_e164', 16)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('daily_close_setting_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_close_setting_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('previous_values')->nullable();
            $table->json('new_values');
            $table->timestamps();

            $table->index(['daily_close_setting_id', 'occurred_at'], 'daily_close_setting_events_subject_time_index');
        });

        Schema::create('daily_close_reports', function (Blueprint $table) {
            $table->id();
            $table->date('operational_date');
            $table->string('recipient_e164', 16)->nullable();
            $table->enum('trigger', ['scheduled', 'manual', 'test', 'download']);
            $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');
            $table->char('idempotency_key', 64)->unique();
            $table->string('pdf_path')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->string('pdf_mime', 100)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('external_media_id')->nullable();
            $table->string('external_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['operational_date', 'status']);
            $table->index(['recipient_e164', 'operational_date']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_close_reports');
        Schema::dropIfExists('daily_close_setting_events');
        Schema::dropIfExists('daily_close_settings');
    }
};
