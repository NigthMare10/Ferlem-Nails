<?php

namespace App\Support;

final class Permissions
{
    public const SALES_ACCESS = 'sales.access';

    public const SALES_CREATE = 'sales.create';

    public const SALES_VIEW_OWN = 'sales.view_own';

    public const SALES_REPRINT = 'sales.reprint';

    public const REPORTS_SALES_VIEW = 'reports.sales.view';

    public const APPOINTMENTS_VIEW_PROJECTION = 'appointments.view_projection';

    public const APPOINTMENTS_ACCESS = 'appointments.access';

    public const APPOINTMENTS_VIEW_OWN = 'appointments.view_own';

    public const APPOINTMENTS_VIEW_ALL = 'appointments.view_all';

    public const APPOINTMENTS_CREATE = 'appointments.create';

    public const APPOINTMENTS_PERFORM = 'appointments.perform';

    public const APPOINTMENTS_UPDATE = 'appointments.update';

    public const APPOINTMENTS_ASSIGN = 'appointments.assign';

    public const APPOINTMENTS_CANCEL = 'appointments.cancel';

    public const APPOINTMENTS_MARK_NO_SHOW = 'appointments.mark_no_show';

    public const APPOINTMENTS_MANAGE_DEPOSIT = 'appointments.manage_deposit';

    public const APPOINTMENTS_RESOLVE_DEPOSIT = 'appointments.resolve_deposit';

    public const APPOINTMENTS_CONVERT_TO_SALE = 'appointments.convert_to_sale';

    public const SETTINGS_ACCESS = 'settings.access';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_ASSIGN_ROLE = 'users.assign_role';

    public const USERS_TOGGLE_STATUS = 'users.toggle_status';

    public const USERS_RESET_PASSWORD = 'users.reset_password';

    public const SERVICES_VIEW = 'services.view';

    public const SERVICES_CREATE = 'services.create';

    public const SERVICES_UPDATE = 'services.update';

    public const SERVICES_DELETE = 'services.delete';

    public const SERVICES_TOGGLE_STATUS = 'services.toggle_status';

    public static function all(): array
    {
        return [self::SALES_ACCESS, self::SALES_CREATE, self::SALES_VIEW_OWN, self::SALES_REPRINT,
            self::REPORTS_SALES_VIEW, self::APPOINTMENTS_VIEW_PROJECTION,
            self::APPOINTMENTS_ACCESS, self::APPOINTMENTS_VIEW_OWN, self::APPOINTMENTS_VIEW_ALL,
            self::APPOINTMENTS_CREATE, self::APPOINTMENTS_PERFORM, self::APPOINTMENTS_UPDATE,
            self::APPOINTMENTS_ASSIGN, self::APPOINTMENTS_CANCEL, self::APPOINTMENTS_MARK_NO_SHOW,
            self::APPOINTMENTS_MANAGE_DEPOSIT, self::APPOINTMENTS_RESOLVE_DEPOSIT,
            self::APPOINTMENTS_CONVERT_TO_SALE,
            self::SETTINGS_ACCESS, self::USERS_VIEW, self::USERS_CREATE, self::USERS_UPDATE,
            self::USERS_ASSIGN_ROLE, self::USERS_TOGGLE_STATUS, self::USERS_RESET_PASSWORD,
            self::SERVICES_VIEW, self::SERVICES_CREATE, self::SERVICES_UPDATE, self::SERVICES_DELETE,
            self::SERVICES_TOGGLE_STATUS];
    }
}
