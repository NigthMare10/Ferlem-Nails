<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->decimal('opening_amount', 12, 2);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('declared_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->string('active_guard', 16)->nullable()->default('OPEN');
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->unique('active_guard', 'cash_sessions_active_guard_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
