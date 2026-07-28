<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_close_settings', function (Blueprint $table) {
            $table->renameColumn('is_enabled', 'enabled');
            $table->json('recipient_emails')->nullable()->after('timezone');
        });

        Schema::table('daily_close_reports', function (Blueprint $table) {
            $table->dropIndex(['recipient_e164', 'operational_date']);
            $table->renameColumn('last_error', 'error_message');
            $table->string('recipient_email', 254)->nullable()->after('operational_date');
            $table->json('summary_snapshot')->nullable()->after('pdf_mime');
            $table->index(['recipient_email', 'operational_date']);
            $table->dropColumn(['recipient_e164', 'external_media_id']);
        });

        Schema::table('daily_close_settings', function (Blueprint $table) {
            $table->dropColumn('recipient_e164');
        });
    }

    public function down(): void
    {
        Schema::table('daily_close_settings', function (Blueprint $table) {
            $table->string('recipient_e164', 16)->nullable()->after('timezone');
        });

        Schema::table('daily_close_reports', function (Blueprint $table) {
            $table->dropIndex(['recipient_email', 'operational_date']);
            $table->renameColumn('error_message', 'last_error');
            $table->string('recipient_e164', 16)->nullable()->after('operational_date');
            $table->string('external_media_id')->nullable()->after('attempts');
            $table->index(['recipient_e164', 'operational_date']);
            $table->dropColumn(['recipient_email', 'summary_snapshot']);
        });

        Schema::table('daily_close_settings', function (Blueprint $table) {
            $table->dropColumn('recipient_emails');
            $table->renameColumn('enabled', 'is_enabled');
        });
    }
};
