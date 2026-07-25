<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('status', ['completed', 'canceled'])->default('completed')->change();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('sales')->where('status', 'canceled')->exists()) {
            throw new RuntimeException('No se puede revertir la migración mientras existan ventas anuladas.');
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canceled_by');
            $table->dropColumn(['canceled_at', 'cancellation_reason']);
            $table->enum('status', ['completed'])->default('completed')->change();
        });
    }
};
