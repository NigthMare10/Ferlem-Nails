<?php

namespace App\Modules\Clientes\Models;

use App\Modules\Agenda\Models\Cita;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'clientes';

    protected $fillable = [
        'public_id',
        'name',
        'phone',
        'email',
        'rtn',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function perfiles(): HasMany
    {
        return $this->hasMany(PerfilCliente::class, 'cliente_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'cliente_id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'cliente_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'cliente_id');
    }
}
