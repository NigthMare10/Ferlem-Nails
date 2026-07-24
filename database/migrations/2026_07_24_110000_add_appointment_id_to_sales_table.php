<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('id')
                ->unique('sales_appointment_id_unique')
                ->constrained('appointments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_appointment_id_unique');
            $table->dropConstrainedForeignId('appointment_id');
        });
    }
};
