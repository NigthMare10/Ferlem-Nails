<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_obligations', function (Blueprint $table) {
            $table->text('processing_error')->nullable()->after('expense_id');
            $table->timestamp('processing_failed_at')->nullable()->after('processing_error');
            $table->unsignedInteger('processing_attempts')->default(0)->after('processing_failed_at');
            $table->index('processing_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_obligations', function (Blueprint $table) {
            $table->dropIndex(['processing_failed_at']);
            $table->dropColumn(['processing_error', 'processing_failed_at', 'processing_attempts']);
        });
    }
};
