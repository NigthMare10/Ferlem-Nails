<?php

namespace App\Modules\Caja\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Caja\Models\MovimientoCaja;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function sesionAbierta(User $user, Sucursal $sucursal): ?SesionCaja
    {
        return SesionCaja::query()
            ->where('user_id', $user->id)
            ->where('sucursal_id', $sucursal->id)
            ->where('status', 'abierta')
            ->latest('opened_at')
            ->first();
    }

    public function abrir(User $user, Sucursal $sucursal, int $openingAmount, ?string $notes, Request $request): SesionCaja
    {
        abort_unless($user->sucursales()->whereKey($sucursal->id)->exists(), 403, 'No puedes abrir caja en una sucursal no asignada.');

        if ($this->sesionAbierta($user, $sucursal)) {
            throw ValidationException::withMessages([
                'opening_amount' => 'Ya existe una sesión de caja abierta para este usuario en la sucursal activa.',
            ]);
        }

        return DB::transaction(function () use ($user, $sucursal, $openingAmount, $notes, $request) {
            $session = SesionCaja::create([
                'sucursal_id' => $sucursal->id,
                'user_id' => $user->id,
                'status' => 'abierta',
                'opening_amount' => $openingAmount,
                'expected_amount' => $openingAmount,
                'opened_at' => now(),
                'notes' => $notes,
            ]);

            MovimientoCaja::create([
                'sesion_caja_id' => $session->id,
                'sucursal_id' => $sucursal->id,
                'user_id' => $user->id,
                'type' => 'apertura',
                'direction' => 'in',
                'amount' => $openingAmount,
                'occurred_at' => now(),
                'notes' => 'Monto inicial de apertura de caja.',
            ]);

            $this->auditService->log(
                action: 'caja.apertura',
                actor: $user,
                branchId: $sucursal->id,
                description: 'Se abrió una nueva sesión de caja.',
                auditable: $session,
                request: $request,
            );

            return $session;
        });
    }

    public function cerrar(SesionCaja $session, User $user, int $countedAmount, ?string $notes, Request $request): SesionCaja
    {
        abort_unless($user->sucursales()->whereKey($session->sucursal_id)->exists(), 403, 'No puedes cerrar cajas de una sucursal no asignada.');

        if ($session->status !== 'abierta') {
            throw ValidationException::withMessages([
                'counted_amount' => 'Solo puedes cerrar sesiones de caja abiertas.',
            ]);
        }

        if ($session->user_id !== $user->id && ! $user->can('caja.cerrar_ajena')) {
            throw ValidationException::withMessages([
                'counted_amount' => 'No puedes cerrar una sesión de caja abierta por otro usuario.',
            ]);
        }

        return DB::transaction(function () use ($session, $user, $countedAmount, $notes, $request) {
            $difference = $countedAmount - (int) $session->expected_amount;

            $session->update([
                'status' => 'cerrada',
                'counted_amount' => $countedAmount,
                'difference_amount' => $difference,
                'closed_at' => now(),
                'notes' => $notes,
            ]);

            MovimientoCaja::create([
                'sesion_caja_id' => $session->id,
                'sucursal_id' => $session->sucursal_id,
                'user_id' => $user->id,
                'type' => 'cierre',
                'direction' => $difference >= 0 ? 'in' : 'out',
                'amount' => abs($difference),
                'occurred_at' => now(),
                'notes' => 'Ajuste generado por el cierre de caja.',
            ]);

            $this->auditService->log(
                action: 'caja.cierre',
                actor: $user,
                branchId: $session->sucursal_id,
                description: 'Se cerró una sesión de caja con validación de reautenticación administrativa.',
                auditable: $session,
                metadata: ['difference_amount' => $difference],
                request: $request,
            );

            return $session->fresh();
        });
    }
}
