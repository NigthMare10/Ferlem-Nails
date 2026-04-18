<?php

namespace App\Modules\Catalogo\Models;

use App\Models\User;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialPrecioServicio extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'historial_precios_servicio';

    protected $fillable = [
        'public_id',
        'servicio_id',
        'sucursal_id',
        'changed_by_user_id',
        'previous_amount',
        'new_amount',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
