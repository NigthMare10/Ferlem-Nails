<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('display_order')->default(0)->index());
        Schema::table('services', function (Blueprint $t) {
            $t->boolean('is_promotion')->default(false)->index();
            $t->unsignedInteger('loyalty_points')->default(0);
        });
        Schema::table('sale_items', function (Blueprint $t) {
            $t->decimal('item_discount_amount', 12, 2)->default(0);
            $t->string('item_discount_type', 12)->nullable();
            $t->decimal('item_discount_value', 12, 2)->default(0);
            $t->string('item_discount_reason', 250)->nullable();
            $t->unsignedInteger('loyalty_points')->default(0);
        });
        Schema::create('employee_breaks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday')->nullable();
            $t->date('date')->nullable();
            $t->time('starts_at');
            $t->time('ends_at');
            $t->timestamps();
            $t->index(['user_id', 'date', 'weekday']);
        });
        Schema::create('security_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audits');
        Schema::dropIfExists('employee_breaks');
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn(['item_discount_amount', 'item_discount_type', 'item_discount_value', 'item_discount_reason', 'loyalty_points']));
        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['is_promotion']);
            $t->dropColumn(['is_promotion', 'loyalty_points']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['display_order']);
            $t->dropColumn('display_order');
        });
    }
};
