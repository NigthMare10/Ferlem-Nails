<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_additional_charges', function (Blueprint $table) {
            $table->foreignId('performed_by')->nullable()->after('amount')->constrained('users')->restrictOnDelete();
            $table->index(['performed_by', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_additional_charges', function (Blueprint $table) {
            $table->dropIndex(['performed_by', 'sale_id']);
            $table->dropConstrainedForeignId('performed_by');
        });
    }
};
