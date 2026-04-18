<?php

namespace App\Modules\Pagos\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Caja\Models\MovimientoCaja;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Pagos\Models\Pago;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function registrar(Orden $order, array $payload, User $user, ?SesionCaja $session, Request $request): Pago
    {
        abort_unless($user->sucursales()->whereKey($order->sucursal_id)->exists(), 403, 'No puedes registrar pagos para una sucursal no asignada.');

        if ($session !== null) {
            if ($order->sucursal_id !== $session->sucursal_id || $session->status !== 'abierta') {
                throw ValidationException::withMessages([
                    'sesion_caja' => 'La sesión de caja no coincide con la sucursal de la orden o no está abierta.',
                ]);
            }

            if ($session->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'sesion_caja' => 'No puedes registrar pagos usando una sesión de caja de otro usuario.',
                ]);
            }
        }

        if ($order->status === 'pagada' || $order->status === 'facturada') {
            throw ValidationException::withMessages([
                'orden' => 'La orden ya fue cobrada previamente.',
            ]);
        }

        if ($payload['idempotency_key'] ?? null) {
            $existing = Pago::query()->where('idempotency_key', $payload['idempotency_key'])->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($order, $payload, $user, $session, $request) {
            $amount = (int) $payload['amount'];

            if ($amount <= 0 || $amount !== (int) $order->total_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto del pago debe coincidir con el total pendiente de la orden.',
                ]);
            }

            $payment = Pago::create([
                'orden_id' => $order->id,
                'sucursal_id' => $order->sucursal_id,
                'user_id' => $user->id,
                'sesion_caja_id' => $session?->id,
                'method' => $payload['method'],
                'status' => 'aplicado',
                'amount' => $amount,
                'reference' => $payload['reference'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'paid_at' => now(),
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $order->update([
                'status' => 'pagada',
                'closed_at' => now(),
                'sesion_caja_id' => $session?->id,
            ]);

            if ($session !== null) {
                $session->increment('expected_amount', $amount);

                MovimientoCaja::create([
                    'sesion_caja_id' => $session->id,
                    'sucursal_id' => $order->sucursal_id,
                    'user_id' => $user->id,
                    'pago_id' => $payment->id,
                    'orden_id' => $order->id,
                    'type' => 'venta',
                    'direction' => 'in',
                    'amount' => $amount,
                    'occurred_at' => now(),
                    'notes' => 'Ingreso de caja por cobro de orden POS.',
                ]);
            }

            $this->auditService->log(
                action: 'pago.registrado',
                actor: $user,
                branchId: $order->sucursal_id,
                description: 'Se registró un pago manual para una orden POS.',
                auditable: $payment,
                request: $request,
            );

            return $payment;
        });
    }
}
