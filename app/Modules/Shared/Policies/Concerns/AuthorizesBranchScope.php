<?php

namespace App\Modules\Shared\Policies\Concerns;

use App\Models\User;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

trait AuthorizesBranchScope
{
    protected function authorizeForBranch(User $user, Sucursal|int $branch, ?string $permission = null): Response
    {
        if ($permission && ! $user->can($permission)) {
            return Response::deny('No tienes permisos suficientes para esta acción.');
        }

        return $this->belongsToBranch($user, $branch)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    protected function authorizeModelInBranch(User $user, Sucursal|int $branch, int $resourceBranchId, ?string $permission = null): Response
    {
        if ($this->resolveBranchId($branch) !== $resourceBranchId) {
            return Response::denyAsNotFound();
        }

        return $this->authorizeForBranch($user, $branch, $permission);
    }

    protected function belongsToBranch(User $user, Sucursal|int $branch): bool
    {
        return $user->hasRole('super_admin')
            || $user->sucursales()->whereKey($this->resolveBranchId($branch))->exists();
    }

    protected function resolveBranchId(Sucursal|int $branch): int
    {
        return $branch instanceof Sucursal ? (int) $branch->id : (int) $branch;
    }
}
