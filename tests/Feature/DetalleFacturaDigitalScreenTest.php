<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Facturacion\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetalleFacturaDigitalScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalle_factura_digital_permanece_disponible_en_el_flujo_pos_usuario_normal(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);

        [, , $invoice] = $this->createInvoiceThroughPosFor($user, $branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stitch/Frame')
                ->where('title', 'Detalle de Factura Digital')
                ->where('invoice.number', $invoice->number));
    }

    public function test_admin_puede_generar_factura_desde_inicio_de_cobro_y_ver_detalle_factura_digital(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();

        [, , $invoice] = $this->createInvoiceThroughPosFor($admin, $branch);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stitch/Frame')
                ->where('title', 'Detalle de Factura Digital')
                ->where('invoice.number', $invoice->number)
                ->where('invoice.operator_name', 'Administracion General'));
    }

    public function test_logout_operativo_para_detalle_factura_digital_redirige_a_login(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);

        $this->createInvoiceThroughPosFor($user, $branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('logout'))
            ->assertRedirect('/login');
    }

    public function test_detalle_factura_digital_serializa_hora_local_de_tegucigalpa(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);

        $this->travelTo(now('UTC')->setDate(2026, 4, 22)->setTime(23, 13, 0));

        try {
            [, , $invoice] = $this->createInvoiceThroughPosFor($user, $branch);

            $this->actingAs($user)
                ->withSession(['active_branch_id' => $branch->id])
                ->get(route('facturas.show', $invoice))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Stitch/Frame')
                    ->where('invoice.issued_timezone', 'America/Tegucigalpa')
                    ->where('invoice.issued_time', '05:13 pm'));
        } finally {
            $this->travelBack();
        }
    }

    private function createInvoiceThroughPosFor(User $user, $branch): array
    {
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
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        return [$branch, $user, Factura::query()->latest('id')->firstOrFail()];
    }
}
