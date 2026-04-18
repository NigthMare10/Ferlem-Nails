<?php

namespace App\Modules\Facturacion\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Facturacion\Models\DetalleFactura;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Facturacion\Models\SecuenciaDocumento;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function emitir(Orden $order, User $user, Request $request): Factura
    {
        abort_unless($user->sucursales()->whereKey($order->sucursal_id)->exists(), 403, 'No puedes emitir facturas para una sucursal no asignada.');

        if ($order->status !== 'pagada') {
            throw ValidationException::withMessages([
                'orden' => 'Solo puedes facturar órdenes pagadas.',
            ]);
        }

        if (Factura::query()->where('orden_id', $order->id)->exists()) {
            throw ValidationException::withMessages([
                'orden' => 'La orden ya tiene una factura emitida.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $request) {
            $sequence = SecuenciaDocumento::query()
                ->where('sucursal_id', $order->sucursal_id)
                ->where('document_type', 'factura')
                ->lockForUpdate()
                ->firstOrFail();

            $nextNumber = $sequence->current_number + 1;
            $formatted = sprintf('%s-%0'.$sequence->padding.'d', $sequence->prefix, $nextNumber);

            $invoice = Factura::create([
                'orden_id' => $order->id,
                'sucursal_id' => $order->sucursal_id,
                'cliente_id' => $order->cliente_id,
                'user_id' => $user->id,
                'secuencia_documento_id' => $sequence->id,
                'number' => $formatted,
                'status' => 'emitida',
                'subtotal_amount' => $order->subtotal_amount,
                'discount_amount' => $order->discount_amount,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
                'currency_code' => 'HNL',
                'issued_at' => now(),
                'metadata' => [
                    'operador_public_id' => $user->public_id,
                ],
            ]);

            foreach ($order->detalles as $detail) {
                DetalleFactura::create([
                    'factura_id' => $invoice->id,
                    'servicio_id' => $detail->servicio_id,
                    'empleado_id' => $detail->empleado_id,
                    'description' => $detail->description,
                    'duration_minutes' => $detail->duration_minutes,
                    'quantity' => $detail->quantity,
                    'unit_price_amount' => $detail->unit_price_amount,
                    'subtotal_amount' => $detail->subtotal_amount,
                    'discount_amount' => $detail->discount_amount,
                    'tax_rate' => $detail->tax_rate,
                    'tax_amount' => $detail->tax_amount,
                    'total_amount' => $detail->total_amount,
                ]);
            }

            $sequence->update(['current_number' => $nextNumber]);
            $order->update(['status' => 'facturada']);

            $this->auditService->log(
                action: 'factura.emitida',
                actor: $user,
                branchId: $order->sucursal_id,
                description: 'Se emitió una factura asociada al perfil autenticado que operó el cobro.',
                auditable: $invoice,
                request: $request,
            );

            return $invoice->load(['detalles']);
        });
    }
}
