<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $request->user()?->can('payroll.configure') && $this->relationLoaded('compensationProfiles')
            ? $this->compensationProfiles->sortByDesc('effective_from')->first(fn ($item) => $item->is_active)
            : null;

        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'is_active' => $this->is_active, 'role' => $this->getRoleNames()->first(),
            'created_at' => $this->created_at?->toISOString(), 'last_login_at' => $this->last_login_at?->toISOString(),
            'employment_profile' => $profile ? [
                'monthly_salary' => $profile->monthly_salary,
                'contract_start_date' => $profile->contract_start_date?->format('Y-m-d'),
                'contract_end_date' => $profile->contract_end_date?->format('Y-m-d'),
                'is_indefinite' => $profile->is_indefinite,
                'default_payment_method' => $profile->default_payment_method,
                'auto_generate_payroll_expense' => $profile->auto_generate_payroll_expense,
            ] : null,
        ];
    }
}
