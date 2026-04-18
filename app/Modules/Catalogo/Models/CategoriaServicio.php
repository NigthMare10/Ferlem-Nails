<?php

namespace App\Modules\Catalogo\Models;

use App\Modules\Shared\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaServicio extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesPublicId;

    protected $table = 'categorias_servicio';

    protected $fillable = [
        'public_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'categoria_servicio_id');
    }
}
