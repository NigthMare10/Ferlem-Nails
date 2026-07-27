<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_compensation_profiles', function (Blueprint $table) {
            $table->date('contract_start_date')->nullable()->after('effective_to');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->boolean('is_indefinite')->default(true)->after('contract_end_date');
            $table->string('default_payment_method', 20)->nullable()->after('is_indefinite');
            $table->boolean('auto_generate_payroll_expense')->default(false)->after('default_payment_method');
        });

        DB::table('employee_compensation_profiles')->orderBy('id')->each(function (object $profile): void {
            DB::table('employee_compensation_profiles')->where('id', $profile->id)->update([
                'contract_start_date' => $profile->effective_from,
                'contract_end_date' => $profile->effective_to,
                'is_indefinite' => $profile->effective_to === null,
                'auto_generate_payroll_expense' => false,
            ]);
        });

        Schema::table('employee_compensation_profiles', function (Blueprint $table) {
            $table->date('contract_start_date')->nullable(false)->change();
            $table->index(['auto_generate_payroll_expense', 'contract_start_date', 'contract_end_date'], 'comp_profile_payroll_contract_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_compensation_profiles', function (Blueprint $table) {
            $table->dropIndex('comp_profile_payroll_contract_index');
            $table->dropColumn([
                'contract_start_date', 'contract_end_date', 'is_indefinite',
                'default_payment_method', 'auto_generate_payroll_expense',
            ]);
        });
    }
};
