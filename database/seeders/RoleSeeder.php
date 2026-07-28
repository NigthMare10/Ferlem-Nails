<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $administrator = Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

        $owner->syncPermissions(Permissions::all());
        $administrator->syncPermissions([
            Permissions::NOTIFICATIONS_ACCESS,
            Permissions::SALES_ACCESS, Permissions::SALES_CREATE, Permissions::SALES_VIEW_OWN, Permissions::SALES_REPRINT,
            Permissions::SALES_VIEW_ALL, Permissions::SALES_VIEW_TRANSFER_PROOF, Permissions::SALES_UPLOAD_TRANSFER_PROOF,
            Permissions::SETTINGS_ACCESS, Permissions::USERS_VIEW, Permissions::USERS_CREATE,
            Permissions::USERS_UPDATE, Permissions::USERS_ASSIGN_ROLE, Permissions::USERS_TOGGLE_STATUS,
            Permissions::USERS_RESET_PASSWORD, Permissions::SERVICES_VIEW, Permissions::SERVICES_CREATE,
            Permissions::SERVICES_UPDATE, Permissions::SERVICES_DELETE, Permissions::SERVICES_TOGGLE_STATUS,
            Permissions::SETTINGS_BUSINESS_HOURS_MANAGE,
            Permissions::APPOINTMENTS_ACCESS, Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_VIEW_ALL, Permissions::APPOINTMENTS_CREATE,
            Permissions::APPOINTMENTS_UPDATE, Permissions::APPOINTMENTS_ASSIGN,
            Permissions::APPOINTMENTS_CANCEL, Permissions::APPOINTMENTS_MARK_NO_SHOW,
            Permissions::APPOINTMENTS_MANAGE_DEPOSIT,
            Permissions::APPOINTMENTS_CONVERT_TO_SALE,
            Permissions::EXPENSES_ACCESS, Permissions::EXPENSES_VIEW,
            Permissions::EXPENSES_CREATE, Permissions::EXPENSES_UPDATE,
            Permissions::EXPENSES_CANCEL, Permissions::EXPENSES_VIEW_ATTACHMENT,
            Permissions::REPORTS_EXPENSES_VIEW,
        ]);
        $employee->syncPermissions([
            Permissions::NOTIFICATIONS_ACCESS,
            Permissions::SALES_ACCESS,
            Permissions::SALES_CREATE,
            Permissions::SALES_VIEW_OWN,
            Permissions::SALES_REPRINT,
            Permissions::SALES_UPLOAD_TRANSFER_PROOF,
            Permissions::APPOINTMENTS_ACCESS,
            Permissions::APPOINTMENTS_VIEW_OWN,
            Permissions::APPOINTMENTS_CREATE,
            Permissions::APPOINTMENTS_PERFORM,
            Permissions::APPOINTMENTS_UPDATE,
            Permissions::APPOINTMENTS_CANCEL,
            Permissions::APPOINTMENTS_MARK_NO_SHOW,
            Permissions::APPOINTMENTS_CONVERT_TO_SALE,
        ]);
    }
}
