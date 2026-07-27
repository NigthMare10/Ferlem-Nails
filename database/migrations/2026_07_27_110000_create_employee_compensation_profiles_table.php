<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_compensation_profiles')) {
            Schema::create('employee_compensation_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->decimal('monthly_salary', 12, 2);
                $table->unsignedTinyInteger('first_payment_day')->default(15);
                $table->string('second_payment_rule')->default('last_day_of_month');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('configured_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }
        Schema::table('employee_compensation_profiles', function (Blueprint $table) {
            $table->index(['user_id', 'effective_from', 'effective_to'], 'comp_profile_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensation_profiles');
    }
};
