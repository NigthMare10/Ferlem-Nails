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

        foreach (Permissions::all() as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::query()->where('name', 'owner')->where('guard_name', 'web')->first()?->givePermissionTo(Permissions::all());
        Role::query()->where('name', 'administrator')->where('guard_name', 'web')->first()?->givePermissionTo([
            Permissions::REPORTS_EXPENSES_VIEW,
            Permissions::EXPENSES_ACCESS,
            Permissions::EXPENSES_VIEW,
            Permissions::EXPENSES_CREATE,
            Permissions::EXPENSES_UPDATE,
            Permissions::EXPENSES_CANCEL,
            Permissions::EXPENSES_VIEW_ATTACHMENT,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are operational data; do not remove them from existing installations.
    }
};
