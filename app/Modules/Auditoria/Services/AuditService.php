<?php

namespace App\Modules\Auditoria\Services;

use App\Models\User;
use App\Modules\Auditoria\Models\AuditoriaEvento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'cookie',
        'authorization',
        'cvv',
        'pan',
    ];

    public function log(
        string $action,
        ?User $actor = null,
        ?int $branchId = null,
        ?string $description = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditoriaEvento {
        return AuditoriaEvento::create([
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'sucursal_id' => $branchId,
            'description' => $description,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);
    }

    protected function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, $this->sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
