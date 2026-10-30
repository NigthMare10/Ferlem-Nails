<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_redemptions', fn (Blueprint $t) => $t->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('loyalty_redemptions', fn (Blueprint $t) => $t->dropConstrainedForeignId('reserved_by'));
    }
};
