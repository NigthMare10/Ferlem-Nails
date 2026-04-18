<?php

namespace App\Modules\Catalogo\Models;

use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servicio extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'servicios';

    protected $fillable = [
        'public_id',
        'categoria_servicio_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'base_price_amount',
        'requires_employee',
        'allow_manual_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_employee' => 'boolean',
            'allow_manual_price' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaServicio::class, 'categoria_servicio_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioServicio::class, 'servicio_id');
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(HistorialPrecioServicio::class, 'servicio_id');
    }

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'empleado_servicio')->withTimestamps();
    }
}
