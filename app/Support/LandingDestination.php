<?php

namespace App\Support;

use App\Models\User;

final class LandingDestination
{
    public function for(User $user): ?string
    {
        if ($this->canAccessHome($user)) {
            return route('home');
        }

        if ($user->hasRole('employee') && $this->canCreateSales($user)) {
            return route('sales.create');
        }

        if ($user->hasRole('administrator')) {
            if ($this->canCreateSales($user)) {
                return route('sales.create');
            }

            if ($this->canNavigateToEarnings($user)) {
                return route('earnings.index');
            }

            if ($this->canNavigateToExpenses($user)) {
                return route('expenses.index');
            }

            if ($user->can(Permissions::SETTINGS_ACCESS) && $user->can(Permissions::USERS_VIEW)) {
                return route('configuration.users.index');
            }

            if ($user->can(Permissions::SETTINGS_ACCESS) && $user->can(Permissions::SERVICES_VIEW)) {
                return route('configuration.services.index');
            }

            return null;
        }

        return null;
    }

    public function canAccessHome(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function canCreateSales(User $user): bool
    {
        return $user->can(Permissions::SALES_ACCESS) && $user->can(Permissions::SALES_CREATE);
    }

    public function canNavigateToSales(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::SALES_ACCESS);
    }

    public function canNavigateToInvoices(User $user): bool
    {
        return SaleAccess::canList($user);
    }

    public function canNavigateToEarnings(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::REPORTS_SALES_VIEW)
            || $user->hasPermissionTo(Permissions::REPORTS_EXPENSES_VIEW);
    }

    public function canNavigateToExpenses(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::EXPENSES_ACCESS)
            && $user->hasPermissionTo(Permissions::EXPENSES_VIEW);
    }

    public function canNavigateToAppointments(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
                || $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL));
    }
}
