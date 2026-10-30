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
        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::where('name', 'owner')->where('guard_name', 'web')->first()?->givePermissionTo(Permissions::all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
