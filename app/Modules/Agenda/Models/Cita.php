<?php

namespace App\Modules\Agenda\Models;

use App\Models\User;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\PerfilCliente;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cita extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'citas';

    protected $fillable = [
        'public_id',
        'sucursal_id',
        'cliente_id',
        'perfil_cliente_id',
        'empleado_id',
        'servicio_id',
        'created_by_user_id',
        'scheduled_start',
        'scheduled_end',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
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

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
