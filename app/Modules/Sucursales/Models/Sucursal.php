<?php

namespace App\Modules\Sucursales\Models;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Clientes\Models\PerfilCliente;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'sucursales';

    protected $fillable = [
        'public_id',
        'code',
        'name',
        'razon_social',
        'rtn',
        'email',
        'phone',
        'address',
        'city',
        'timezone',
        'currency_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(ConfiguracionSucursal::class, 'sucursal_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sucursal_user')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function perfilesCliente(): HasMany
    {
        return $this->hasMany(PerfilCliente::class, 'sucursal_id');
    }

    public function sesionesCaja(): HasMany
    {
        return $this->hasMany(SesionCaja::class, 'sucursal_id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'sucursal_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'sucursal_id');
    }
}
