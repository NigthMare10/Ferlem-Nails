<?php

namespace App\Modules\Clientes\Models;

use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilCliente extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'perfiles_cliente';

    protected $fillable = [
        'public_id',
        'cliente_id',
        'sucursal_id',
        'alias',
        'alertas',
        'preferencias',
        'saldo_a_favor',
        'ticket_promedio',
        'ultima_visita_at',
    ];

    protected function casts(): array
    {
        return [
            'ultima_visita_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
