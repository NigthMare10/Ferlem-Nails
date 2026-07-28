<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->where('name', Permissions::NOTIFICATIONS_ACCESS)->where('guard_name', 'web')->first();
        $employee = Role::query()->where('name', 'employee')->where('guard_name', 'web')->first();
        if ($permission && $employee) {
            $employee->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $employee = Role::query()->where('name', 'employee')->where('guard_name', 'web')->first();
        $employee?->revokePermissionTo(Permissions::NOTIFICATIONS_ACCESS);
    }
};
