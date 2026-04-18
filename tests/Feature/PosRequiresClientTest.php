<?php

namespace Tests\Feature;

use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosRequiresClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_flujo_operativo_no_exige_cliente_para_cobrar(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);
        $service = Servicio::query()->firstOrFail();

        SesionCaja::create([
            'sucursal_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'abierta',
            'opening_amount' => 50000,
            'expected_amount' => 50000,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('pos.checkout'), [
                'items' => [
                    ['servicio_public_id' => $service->public_id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect();

        $order = Orden::query()->latest('id')->firstOrFail();
        $invoice = Factura::query()->latest('id')->firstOrFail();

        $this->assertNull($order->cliente_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertNull($invoice->cliente_id);
        $this->assertSame($user->id, $invoice->user_id);
    }
}
