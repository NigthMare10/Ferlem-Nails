<?php

namespace App\Modules\Facturacion\Models;

use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleFactura extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'detalle_facturas';

    protected $fillable = [
        'public_id',
        'factura_id',
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

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
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
