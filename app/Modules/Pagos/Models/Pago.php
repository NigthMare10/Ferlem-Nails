<?php

namespace App\Modules\Pagos\Models;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'pagos';

    protected $fillable = [
        'public_id',
        'orden_id',
        'sucursal_id',
        'user_id',
        'sesion_caja_id',
        'method',
        'status',
        'amount',
        'reference',
        'idempotency_key',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }
}
