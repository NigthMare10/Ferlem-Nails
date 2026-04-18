<?php

namespace Tests\Feature;

use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Pagos\Models\Pago;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_operativo_pos_queda_asociado_al_perfil_autenticado(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);
        $service = Servicio::query()->firstOrFail();

        $cashSession = SesionCaja::create([
            'sucursal_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'abierta',
            'opening_amount' => 100000,
            'expected_amount' => 100000,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('pos.checkout'), [
                'items' => [
                    [
                        'servicio_public_id' => $service->public_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $order = Orden::query()->latest('id')->firstOrFail();
        $payment = Pago::query()->latest('id')->firstOrFail();
        $invoice = Factura::query()->latest('id')->firstOrFail();

        $this->assertNull($order->cliente_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('facturada', $order->status);
        $this->assertSame($order->id, $payment->orden_id);
        $this->assertSame($user->id, $payment->user_id);
        $this->assertSame($cashSession->id, $payment->sesion_caja_id);
        $this->assertNull($invoice->cliente_id);
        $this->assertSame($order->id, $invoice->orden_id);
        $this->assertSame($user->id, $invoice->user_id);
        $this->assertStringStartsWith('FNL-', $invoice->number);
    }

    public function test_perfil_prueba_puede_pagar_y_ver_factura_sin_403(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);
        $service = Servicio::query()->firstOrFail();

        SesionCaja::create([
            'sucursal_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'abierta',
            'opening_amount' => 100000,
            'expected_amount' => 100000,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('pos.checkout'), [
                'items' => [
                    [
                        'servicio_public_id' => $service->public_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $invoice = Factura::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.show', $invoice))
            ->assertOk()
            ->assertSee($service->name)
            ->assertSee('L 517.50');
    }

    public function test_puede_repetir_el_mismo_servicio_y_la_factura_muestra_la_cantidad_real(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);
        $service = Servicio::query()->firstOrFail();

        SesionCaja::create([
            'sucursal_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'abierta',
            'opening_amount' => 100000,
            'expected_amount' => 100000,
            'opened_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('pos.checkout'), [
                'items' => [
                    [
                        'servicio_public_id' => $service->public_id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = Factura::query()->latest('id')->firstOrFail();

        $this->assertSame(2, $invoice->detalles()->firstOrFail()->quantity);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.show', $invoice))
            ->assertOk()
            ->assertSee($service->name)
            ->assertSee('"quantity":2')
            ->assertSee('L 1,035.00');
    }

    public function test_puede_reducir_cantidad_sin_bajar_de_cero_en_el_resumen(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);

        $response = $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('pos.detail', 'unas'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->where('initialCategory', 'unas'));
    }
}
