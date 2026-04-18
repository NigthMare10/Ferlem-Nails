<?php

namespace App\Models;

use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['public_id', 'name', 'email', 'phone', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use UsesPublicId;

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_user')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(\App\Modules\Auditoria\Models\AuditoriaEvento::class, 'actor_user_id');
    }

    public function empleado(): HasOne
    {
        return $this->hasOne(Empleado::class, 'user_id');
    }
}
