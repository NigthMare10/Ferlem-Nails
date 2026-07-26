<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'transfer'])->change();
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->enum('method', ['cash', 'card', 'transfer'])->change();
            $table->string('proof_path')->nullable()->after('appointment_deposit_id');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->string('proof_mime', 100)->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime');
            $table->foreignId('proof_uploaded_by')->nullable()->after('proof_size')->constrained('users')->restrictOnDelete();
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_uploaded_by');
        });

        $permission = Permission::firstOrCreate(['name' => 'sales.view_transfer_proof', 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['owner', 'administrator'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (DB::table('sales')->where('payment_method', 'transfer')->exists()
            || DB::table('sale_payments')->where('method', 'transfer')->exists()) {
            throw new RuntimeException('No se puede retirar Transferencia mientras existan pagos que la utilizan.');
        }

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proof_uploaded_by');
            $table->dropColumn(['proof_path', 'proof_original_name', 'proof_mime', 'proof_size', 'proof_uploaded_at']);
            $table->enum('method', ['cash', 'card'])->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card'])->change();
        });

        Permission::query()->where('name', 'sales.view_transfer_proof')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
