<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_obligations')) {
            Schema::create('payroll_obligations', function (Blueprint $table) {
                $table->id();
                $table->string('obligation_number')->nullable()->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('compensation_profile_id')->constrained('employee_compensation_profiles')->restrictOnDelete();
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->enum('installment', ['first', 'second']);
                $table->date('scheduled_date');
                $table->decimal('amount', 12, 2);
                $table->enum('status', ['pending', 'paid', 'canceled'])->default('pending');
                $table->timestamp('generated_at');
                $table->foreignId('generated_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses')->restrictOnDelete();
                $table->timestamp('canceled_at')->nullable();
                $table->foreignId('canceled_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->index(['status', 'scheduled_date']);
            });
        }
        Schema::table('payroll_obligations', function (Blueprint $table) {
            $table->unique(['user_id', 'period_year', 'period_month', 'installment'], 'payroll_period_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_obligations');
    }
};
