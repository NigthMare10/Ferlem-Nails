<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_redemptions', function (Blueprint $t) {
            $t->dateTime('resolved_at')->nullable();
            $t->string('resolution_note', 500)->nullable();
        });
        Schema::table('loyalty_gift_batches', fn (Blueprint $t) => $t->index(['expires_at', 'id'], 'gift_expiry_lookup'));
    }

    public function down(): void
    {
        Schema::table('loyalty_redemptions', fn (Blueprint $t) => $t->dropColumn(['resolved_at', 'resolution_note']));
        Schema::table('loyalty_gift_batches', fn (Blueprint $t) => $t->dropIndex('gift_expiry_lookup'));
    }
};
