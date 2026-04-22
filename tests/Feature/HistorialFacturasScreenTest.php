<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialFacturasScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_historial_facturas_renderiza_datos_y_rutas_reales(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $invoice = $this->createInvoiceThroughPosFor($admin, $branch);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stitch/Frame')
                ->where('title', 'Historial de Facturas')
                ->where('invoiceHistory.routes.export', route('facturas.export', absolute: false))
                ->where('invoiceHistory.routes.latest_invoice', route('facturas.show', $invoice, absolute: false))
                ->where('invoiceHistory.invoices.0.number', $invoice->number)
                ->where('invoiceHistory.invoices.0.detail_url', route('facturas.show', $invoice, absolute: false)));
    }

    public function test_historial_facturas_exporta_csv_real(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $invoice = $this->createInvoiceThroughPosFor($admin, $branch);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($invoice->number, $response->streamedContent());
        $this->assertStringContainsString('Administracion General', $response->streamedContent());
    }

    public function test_logout_real_sigue_disponible_desde_contexto_historial_facturas(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('logout'))
            ->assertRedirect('/login');
    }

    private function createInvoiceThroughPosFor(User $user, Sucursal $branch): Factura
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

        return Factura::query()->latest('issued_at')->firstOrFail();
    }
}
