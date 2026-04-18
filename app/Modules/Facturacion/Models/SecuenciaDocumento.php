<?php

namespace App\Modules\Facturacion\Models;

use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecuenciaDocumento extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'secuencias_documento';

    protected $fillable = [
        'public_id',
        'sucursal_id',
        'document_type',
        'prefix',
        'current_number',
        'padding',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'secuencia_documento_id');
    }
}
