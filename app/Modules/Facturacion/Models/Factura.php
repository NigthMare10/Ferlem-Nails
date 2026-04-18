<?php

namespace App\Modules\Facturacion\Models;

use App\Models\User;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'facturas';

    protected $fillable = [
        'public_id',
        'orden_id',
        'sucursal_id',
        'cliente_id',
        'user_id',
        'secuencia_documento_id',
        'number',
        'status',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency_code',
        'issued_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function secuencia(): BelongsTo
    {
        return $this->belongsTo(SecuenciaDocumento::class, 'secuencia_documento_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleFactura::class, 'factura_id');
    }
}
