<?php

namespace App\Modules\VentasPOS\Models;

use App\Models\User;
use App\Modules\Agenda\Models\Cita;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\PerfilCliente;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orden extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'ordenes';

    protected $fillable = [
        'public_id',
        'sucursal_id',
        'cliente_id',
        'perfil_cliente_id',
        'cita_id',
        'sesion_caja_id',
        'user_id',
        'status',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency_code',
        'notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function perfilCliente(): BelongsTo
    {
        return $this->belongsTo(PerfilCliente::class, 'perfil_cliente_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrden::class, 'orden_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'orden_id');
    }

    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class, 'orden_id');
    }
}
