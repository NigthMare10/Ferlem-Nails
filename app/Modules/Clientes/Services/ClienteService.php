<?php

namespace App\Modules\Clientes\Services;

use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\PerfilCliente;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function paginarParaSucursal(Sucursal $sucursal, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Cliente::query()
            ->with(['perfiles' => fn ($query) => $query->where('sucursal_id', $sucursal->id)])
            ->whereHas('perfiles', fn ($query) => $query->where('sucursal_id', $sucursal->id))
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(array $payload, Sucursal $sucursal, ?\App\Models\User $actor = null, ?\Illuminate\Http\Request $request = null): Cliente
    {
        return DB::transaction(function () use ($payload, $sucursal, $actor, $request) {
            $client = Cliente::create([
                'name' => $payload['name'],
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
                'rtn' => $payload['rtn'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'is_active' => Arr::get($payload, 'is_active', true),
            ]);

            $profile = PerfilCliente::create([
                'cliente_id' => $client->id,
                'sucursal_id' => $sucursal->id,
                'alias' => $payload['alias'] ?? null,
                'alertas' => $payload['alertas'] ?? null,
                'preferencias' => $payload['preferencias'] ?? null,
            ]);

            $this->auditService->log(
                action: 'clientes.creado',
                actor: $actor,
                branchId: $sucursal->id,
                description: 'Se creó un nuevo cliente con perfil operativo en la sucursal.',
                auditable: $client,
                metadata: [
                    'perfil_public_id' => $profile->public_id,
                ],
                request: $request,
            );

            return $client->load('perfiles');
        });
    }

    public function update(Cliente $client, array $payload, Sucursal $sucursal, ?\App\Models\User $actor = null, ?\Illuminate\Http\Request $request = null): Cliente
    {
        return DB::transaction(function () use ($client, $payload, $sucursal, $actor, $request) {
            $client->update([
                'name' => $payload['name'],
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
                'rtn' => $payload['rtn'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'is_active' => Arr::get($payload, 'is_active', true),
            ]);

            $profile = $client->perfiles()->firstOrCreate([
                'sucursal_id' => $sucursal->id,
            ]);

            $profile->fill([
                'alias' => $payload['alias'] ?? null,
                'alertas' => $payload['alertas'] ?? null,
                'preferencias' => $payload['preferencias'] ?? null,
            ])->save();

            $this->auditService->log(
                action: 'clientes.actualizado',
                actor: $actor,
                branchId: $sucursal->id,
                description: 'Se actualizó información crítica del cliente en la sucursal activa.',
                auditable: $client,
                metadata: [
                    'perfil_public_id' => $profile->public_id,
                ],
                request: $request,
            );

            return $client->fresh(['perfiles']);
        });
    }

    public function altaRapida(array $payload, Sucursal $sucursal, ?\App\Models\User $actor = null, ?\Illuminate\Http\Request $request = null): Cliente
    {
        return $this->store([
            'name' => $payload['name'],
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'alias' => $payload['alias'] ?? null,
        ], $sucursal, $actor, $request);
    }
}
