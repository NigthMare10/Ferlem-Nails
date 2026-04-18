<?php

namespace App\Modules\Sucursales\Models;

use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionSucursal extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'configuraciones_sucursal';

    protected $fillable = [
        'public_id',
        'sucursal_id',
        'currency_code',
        'currency_symbol',
        'impuesto_nombre',
        'impuesto_porcentaje',
        'ventana_reautenticacion_minutos',
        'descuento_sin_reautenticacion_porcentaje',
        'permitir_precio_manual',
    ];

    protected function casts(): array
    {
        return [
            'impuesto_porcentaje' => 'decimal:2',
            'permitir_precio_manual' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
