<?php

namespace App\Modules\Caja\Models;

use App\Models\User;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionCaja extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'sesiones_caja';

    protected $fillable = [
        'public_id',
        'sucursal_id',
        'user_id',
        'status',
        'opening_amount',
        'expected_amount',
        'counted_amount',
        'difference_amount',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'sesion_caja_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'sesion_caja_id');
    }
}
