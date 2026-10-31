<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_redemptions', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'loyalty_expiry_lookup');
            $table->index(['customer_id', 'loyalty_reward_id', 'status'], 'loyalty_customer_reward_usage');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_redemptions', function (Blueprint $table) {
            $table->dropIndex('loyalty_expiry_lookup');
            $table->dropIndex('loyalty_customer_reward_usage');
        });
    }
};
