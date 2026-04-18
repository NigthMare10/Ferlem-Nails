<?php

namespace App\Modules\Empleados\Models;

use App\Models\User;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'empleados';

    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role_title',
        'hire_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'empleado_sucursal')
            ->withPivot(['role_title', 'is_primary'])
            ->withTimestamps();
    }

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'empleado_servicio')->withTimestamps();
    }
}
