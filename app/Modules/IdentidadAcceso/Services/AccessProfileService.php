<?php

namespace App\Modules\IdentidadAcceso\Services;

use App\Models\User;
use App\Modules\IdentidadAcceso\Support\SecurityRoles;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Support\Collection;

class AccessProfileService
{
    public function visibleProfiles(): Collection
    {
        return User::query()
            ->with([
                'empleado',
                'sucursales' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            ])
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) {
                $query->whereDoesntHave('empleado')
                    ->orWhereHas('empleado', fn ($employeeQuery) => $employeeQuery->where('is_active', true));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->mapProfile($user))
            ->values();
    }

    public function resolveAuthenticableProfile(string $profilePublicId): ?User
    {
        return User::query()
            ->with([
                'empleado',
                'sucursales' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            ])
            ->where('public_id', $profilePublicId)
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) {
                $query->whereDoesntHave('empleado')
                    ->orWhereHas('empleado', fn ($employeeQuery) => $employeeQuery->where('is_active', true));
            })
            ->first();
    }

    public function resolveActiveBranchAfterLogin(User $user): ?Sucursal
    {
        $branches = $user->sucursales()->where('is_active', true)->orderBy('name')->get();

        if ($branches->count() === 1) {
            return $branches->first();
        }

        $defaultBranch = $branches->first(fn (Sucursal $branch) => (bool) $branch->pivot?->is_default);

        return $defaultBranch instanceof Sucursal ? $defaultBranch : null;
    }

    public function resolvePostLoginPath(User $user, ?Sucursal $activeBranch): string
    {
        if ($activeBranch === null && $user->sucursales()->where('is_active', true)->count() > 1) {
            return route('sucursales.selector', absolute: false);
        }

        if ($user->hasAnyRole(SecurityRoles::administrativeRoles())) {
            return route('dashboard', absolute: false);
        }

        if ($user->hasRole('auditor') || $user->can('reportes.ver_global') || $user->can('reportes.ver_sucursal')) {
            return route('reportes.index', absolute: false);
        }

        if ($user->hasAnyRole(['cajero', 'tecnica', 'recepcionista']) || $user->can('pos.usar')) {
            return route('pos.index', absolute: false);
        }

        if ($user->can('facturas.ver')) {
            return route('facturas.index', absolute: false);
        }

        if ($user->can('clientes.ver')) {
            return route('clientes.index', absolute: false);
        }

        return route('dashboard', absolute: false);
    }

    protected function mapProfile(User $user): array
    {
        $employee = $user->empleado;
        $branches = $user->sucursales;
        $primaryRole = $user->getRoleNames()->first();
        $displayName = $employee?->name ?: $user->name;
        $roleTitle = $employee?->role_title ?: $this->humanizeRole($primaryRole);

        return [
            'public_id' => $user->public_id,
            'display_name' => $displayName,
            'role_key' => $primaryRole,
            'role_label' => $roleTitle,
            'branch_names' => $branches->pluck('name')->values()->all(),
            'branch_count' => $branches->count(),
            'initials' => $this->initials($displayName),
            'is_active' => $user->is_active,
            'status_label' => $user->is_active ? 'Disponible' : 'No disponible',
        ];
    }

    protected function humanizeRole(?string $role): string
    {
        return match ($role) {
            'super_admin' => 'Superadministracion',
            'admin_negocio' => 'Administracion del negocio',
            'gerente_sucursal' => 'Gerencia de sucursal',
            'cajero' => 'Caja y cobro',
            'tecnica' => 'Tecnica especialista',
            'recepcionista' => 'Recepcion operativa',
            'auditor' => 'Auditoria y control',
            'administrador' => 'Administracion legacy',
            'gerencia' => 'Gerencia legacy',
            default => 'Perfil operativo',
        };
    }

    protected function initials(string $name): string
    {
        return collect(explode(' ', preg_replace('/\s+/', ' ', trim($name))))
            ->filter()
            ->take(2)
            ->map(fn (string $segment) => mb_strtoupper(mb_substr($segment, 0, 1)))
            ->implode('');
    }
}
