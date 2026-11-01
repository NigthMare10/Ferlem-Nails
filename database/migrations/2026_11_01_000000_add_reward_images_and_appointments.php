<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_rewards', fn (Blueprint $t) => $t->string('image_path')->nullable());
        Schema::table('appointments', function (Blueprint $t) {
            $t->foreignId('loyalty_redemption_id')->nullable()->unique()->constrained('loyalty_redemptions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'loyalty_redemption_id')) {
            Schema::table('appointments', function (Blueprint $t) {
                $t->dropUnique(['loyalty_redemption_id']);
                $t->dropConstrainedForeignId('loyalty_redemption_id');
            });
        }
        if (Schema::hasColumn('loyalty_rewards', 'image_path')) {
            Schema::table('loyalty_rewards', fn (Blueprint $t) => $t->dropColumn('image_path'));
        }
    }
};
