<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_rewards', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        Schema::table('loyalty_settings', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        Schema::create('loyalty_gift_batches', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_token')->unique();
            $t->string('payload_hash', 64);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('type', 16);
            $t->unsignedInteger('points')->nullable();
            $t->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('service_snapshot')->nullable();
            $t->string('reason', 500);
            $t->string('message', 500)->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('loyalty_gift_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('loyalty_gift_batches')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('pending');
            $t->string('error', 300)->nullable();
            $t->dateTime('applied_at')->nullable();
            $t->dateTime('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('resolution_note', 500)->nullable();
            $t->timestamps();
            $t->unique(['batch_id', 'customer_id']);
            $t->index(['customer_id', 'status', 'id'], 'gift_customer_status');
            $t->index(['status', 'batch_id'], 'gift_pending_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_gift_recipients');
        Schema::dropIfExists('loyalty_gift_batches');
        Schema::table('loyalty_rewards', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::table('loyalty_settings', fn (Blueprint $t) => $t->dropColumn('version'));
    }
};
