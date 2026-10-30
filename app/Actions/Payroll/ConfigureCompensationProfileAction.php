<?php

namespace App\Actions\Payroll;

use App\Models\EmployeeCompensationProfile;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollAudit;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigureCompensationProfileAction
{
    public function execute(User $actor, User $employee, array $data, bool $allowInactive = false): EmployeeCompensationProfile
    {
        if (! $actor->is_active || ! $actor->can(Permissions::PAYROLL_CONFIGURE)) {
            throw new AuthorizationException;
        }
        if (! $allowInactive && ! $employee->is_active) {
            throw ValidationException::withMessages(['user' => 'El usuario debe estar activo para configurar compensación.']);
        }

        return DB::transaction(function () use ($actor, $employee, $data) {
            $active = EmployeeCompensationProfile::query()->where('user_id', $employee->id)->where('is_active', true)->lockForUpdate()->get();
            foreach ($active as $profile) {
                if ($profile->effective_from->lte($data['effective_from']) && (! $profile->effective_to || $profile->effective_to->gte($data['effective_from']))) {
                    $profile->effective_to = CarbonImmutable::parse($data['effective_from'], 'America/Tegucigalpa')->subDay()->toDateString();
                    $profile->is_active = false;
                    $profile->save();
                    PayrollAudit::record($profile, 'profile.closed', $actor, ['effective_to' => null, 'is_active' => true], ['effective_to' => $profile->effective_to->toDateString(), 'is_active' => false]);
                }
            }
            $profile = new EmployeeCompensationProfile;
            $profile->user_id = $employee->id;
            $profile->monthly_salary = Money::fromCents(Money::toCents((string) $data['monthly_salary']));
            $profile->first_payment_day = 15;
            $profile->second_payment_rule = 'last_day_of_month';
            $profile->effective_from = $data['effective_from'];
            $profile->effective_to = $data['effective_to'] ?? null;
            $profile->contract_start_date = $data['contract_start_date'] ?? $data['effective_from'];
            $profile->is_indefinite = (bool) ($data['is_indefinite'] ?? ($data['contract_end_date'] ?? null) === null);
            $profile->contract_end_date = $profile->is_indefinite ? null : ($data['contract_end_date'] ?? null);
            $profile->default_payment_method = $data['default_payment_method'] ?? null;
            $profile->auto_generate_payroll_expense = (bool) ($data['auto_generate_payroll_expense'] ?? false);
            $profile->is_active = true;
            $profile->notes = $data['notes'] ?? null;
            $profile->configured_by = $actor->id;
            $profile->save();
            PayrollAudit::record($profile, 'profile.created', $actor, [], [
                'monthly_salary' => $profile->monthly_salary,
                'first_payment_day' => $profile->first_payment_day,
                'second_payment_rule' => $profile->second_payment_rule,
                'effective_from' => $profile->effective_from->toDateString(),
                'effective_to' => $profile->effective_to?->toDateString(),
                'contract_start_date' => $profile->contract_start_date->toDateString(),
                'contract_end_date' => $profile->contract_end_date?->toDateString(),
                'is_indefinite' => $profile->is_indefinite,
                'default_payment_method' => $profile->default_payment_method,
                'auto_generate_payroll_expense' => $profile->auto_generate_payroll_expense,
                'is_active' => true,
            ]);

            return $profile;
        }, 3);
    }
}
