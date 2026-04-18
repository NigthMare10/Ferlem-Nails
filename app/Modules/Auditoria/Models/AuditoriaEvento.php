<?php

namespace App\Modules\Auditoria\Models;

use App\Models\User;
use App\Modules\Shared\Models\Concerns\UsesPublicId;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaEvento extends Model
{
    use HasFactory;
    use UsesPublicId;

    protected $table = 'auditoria_eventos';

    protected $fillable = [
        'public_id',
        'actor_user_id',
        'sucursal_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
