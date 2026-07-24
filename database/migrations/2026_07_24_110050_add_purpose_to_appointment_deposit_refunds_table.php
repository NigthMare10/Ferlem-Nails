<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_deposit_refunds', function (Blueprint $table) {
            $table->enum('purpose', ['terminal', 'excess'])->default('terminal')->after('amount');
            $table->index(['appointment_deposit_id', 'purpose'], 'deposit_refunds_deposit_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_deposit_refunds', function (Blueprint $table) {
            $table->dropIndex('deposit_refunds_deposit_purpose_index');
            $table->dropColumn('purpose');
        });
    }
};
