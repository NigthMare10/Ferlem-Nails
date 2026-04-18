<?php

namespace App\Modules\Caja\Models;

use App\Models\User;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'public_id',
        'sesion_caja_id',
        'sucursal_id',
        'user_id',
        'pago_id',
        'orden_id',
        'type',
        'direction',
        'amount',
        'occurred_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }
}
