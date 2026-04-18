<?php

namespace App\Modules\VentasPOS\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Catalogo\Services\CatalogoService;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\DetalleOrden;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected CatalogoService $catalogoService,
        protected AuditService $auditService,
    ) {
    }

    public function crear(array $payload, Sucursal $sucursal, User $user, ?int $cashSessionId, Request $request): Orden
    {
        abort_unless($user->sucursales()->whereKey($sucursal->id)->exists(), 403, 'No puedes crear órdenes en una sucursal no asignada.');

        if (empty($payload['items']) || ! is_array($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Debes seleccionar al menos un servicio para la orden.',
            ]);
        }

        if ($cashSessionId !== null) {
            $cashSession = SesionCaja::query()->findOrFail($cashSessionId);

            if ($cashSession->sucursal_id !== $sucursal->id || $cashSession->status !== 'abierta') {
                throw ValidationException::withMessages([
                    'sesion_caja' => 'La sesión de caja indicada no pertenece a la sucursal activa o no está abierta.',
                ]);
            }

            if ($cashSession->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'sesion_caja' => 'No puedes operar con una sesión de caja abierta por otro usuario.',
                ]);
            }
        }

        return DB::transaction(function () use ($payload, $sucursal, $user, $cashSessionId, $request) {
            $taxRate = (float) ($sucursal->configuracion?->impuesto_porcentaje ?? 15.00);

            $order = Orden::create([
                'sucursal_id' => $sucursal->id,
                'sesion_caja_id' => $cashSessionId,
                'user_id' => $user->id,
                'status' => 'pendiente_pago',
                'currency_code' => 'HNL',
                'notes' => $payload['notes'] ?? null,
            ]);

            $subtotal = 0;
            $tax = 0;
            $discount = (int) ($payload['discount_amount'] ?? 0);

            foreach ($payload['items'] as $item) {
                /** @var Servicio $service */
                $service = Servicio::query()->findOrFail($item['servicio_id']);

                if (! $service->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'No puedes usar servicios inactivos en una orden.',
                    ]);
                }

                if (! empty($item['empleado_id'])) {
                    $employee = Empleado::query()->findOrFail($item['empleado_id']);

                    if (! $employee->sucursales()->where('sucursal_id', $sucursal->id)->exists()) {
                        throw ValidationException::withMessages([
                            'items' => 'El empleado seleccionado no pertenece a la sucursal activa.',
                        ]);
                    }

                    if (! $employee->servicios()->where('servicio_id', $service->id)->exists()) {
                        throw ValidationException::withMessages([
                            'items' => 'El empleado seleccionado no está habilitado para el servicio indicado.',
                        ]);
                    }
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = $this->catalogoService->precioVigente($service, $sucursal);
                $lineSubtotal = $unitPrice * $quantity;
                $lineTax = (int) round($lineSubtotal * ($taxRate / 100));
                $lineTotal = $lineSubtotal + $lineTax;

                DetalleOrden::create([
                    'orden_id' => $order->id,
                    'servicio_id' => $service->id,
                    'empleado_id' => $item['empleado_id'] ?? null,
                    'description' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'quantity' => $quantity,
                    'unit_price_amount' => $unitPrice,
                    'subtotal_amount' => $lineSubtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $tax += $lineTax;
            }

            if ($discount > 0 && $subtotal > 0) {
                $allowedPercent = (int) ($sucursal->configuracion?->descuento_sin_reautenticacion_porcentaje ?? 10);
                $discountPercent = ($discount / $subtotal) * 100;
                $confirmedAt = $request->hasSession() ? $request->session()->get('admin_reauthenticated_at') : null;
                $confirmedUserId = $request->hasSession() ? (int) $request->session()->get('admin_reauthenticated_user_id') : 0;
                $confirmedBranchId = $request->hasSession() ? (int) $request->session()->get('admin_reauthenticated_branch_id') : 0;
                $windowMinutes = (int) ($sucursal->configuracion?->ventana_reautenticacion_minutos ?? 15);
                $reauthStillValid = $confirmedAt
                    && now()->diffInMinutes($confirmedAt) < $windowMinutes
                    && $confirmedUserId === (int) $user->id
                    && $confirmedBranchId === (int) $sucursal->id;

                if ($discountPercent > $allowedPercent && ! ($request->user()?->can('pos.aplicar_descuento_extraordinario') && $reauthStillValid)) {
                    throw ValidationException::withMessages([
                        'discount_amount' => 'El descuento supera la política permitida y requiere reautenticación administrativa vigente.',
                    ]);
                }
            }

            $total = max(0, $subtotal + $tax - $discount);

            $order->update([
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total_amount' => $total,
            ]);

            $this->auditService->log(
                action: 'orden.creada',
                actor: $user,
                branchId: $sucursal->id,
                description: 'Se creó una orden de POS asociada al perfil autenticado.',
                auditable: $order,
                request: $request,
            );

            return $order->load(['detalles.servicio']);
        });
    }
}
