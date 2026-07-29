<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()->firstOrCreate([
            'name' => Permissions::SALES_APPLY_FREQUENT_DISCOUNT,
            'guard_name' => 'web',
        ]);

        Role::query()->where('name', 'owner')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        Role::query()->where('name', 'administrator')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        Role::query()->where('name', 'employee')->where('guard_name', 'web')->first()?->revokePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('name', Permissions::SALES_APPLY_FREQUENT_DISCOUNT)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
