<?php

namespace App\Modules\VentasPOS\Models;

use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleOrden extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'detalle_ordenes';

    protected $fillable = [
        'public_id',
        'orden_id',
        'servicio_id',
        'empleado_id',
        'description',
        'duration_minutes',
        'quantity',
        'unit_price_amount',
        'subtotal_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }
}
