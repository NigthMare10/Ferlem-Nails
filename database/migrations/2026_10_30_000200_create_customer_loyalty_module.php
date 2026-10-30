<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('phone', 30)->nullable();
            $t->date('birthday')->nullable();
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('customer_password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::table('sales', fn (Blueprint $t) => $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('appointments', fn (Blueprint $t) => $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete());
        Schema::create('loyalty_rewards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->unsignedInteger('points_cost');
            $t->string('benefit_type', 30)->default('gift');
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('discount_percent', 5, 2)->nullable();
            $t->unsignedInteger('stock')->nullable();
            $t->unsignedInteger('per_customer_limit')->default(1);
            $t->boolean('birthday_only')->default(false);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('loyalty_redemptions', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_token')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('loyalty_reward_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('points_cost');
            $t->string('status', 20)->default('reserved');
            $t->json('reward_snapshot');
            $t->timestamp('expires_at');
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['customer_id', 'status']);
        });
        Schema::create('loyalty_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_item_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('redemption_id')->nullable()->constrained('loyalty_redemptions')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 25);
            $t->integer('points');
            $t->integer('qualifying_points')->default(0);
            $t->string('source_key')->unique();
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'created_at']);
        });
        Schema::create('customer_qr_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_favorites', function (Blueprint $t) {
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->unique(['customer_id', 'service_id']);
        });
        Schema::create('loyalty_settings', function (Blueprint $t) {
            $t->id();
            $t->json('levels');
            $t->timestamps();
        });
        DB::table('loyalty_settings')->insert(['id' => 1, 'levels' => json_encode([
            ['name' => 'Brillo', 'points' => 0, 'benefits' => 'Acumula puntos por tus servicios'],
            ['name' => 'Glow', 'points' => 100, 'benefits' => 'Promociones exclusivas'],
            ['name' => 'Lemus VIP', 'points' => 200, 'benefits' => 'Beneficios especiales'],
            ['name' => 'Icon', 'points' => 350, 'benefits' => 'Experiencias exclusivas'],
        ]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (['loyalty_settings', 'customer_favorites', 'customer_qr_tokens', 'loyalty_entries', 'loyalty_redemptions', 'loyalty_rewards'] as $table) {
            Schema::dropIfExists($table);
        }
        if (Schema::hasColumn('sales', 'customer_id')) {
            Schema::table('sales', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        }
        if (Schema::hasColumn('appointments', 'customer_id')) {
            Schema::table('appointments', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        }
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customers');
    }
};
