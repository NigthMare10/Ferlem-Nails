<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Catalogo\Models\PrecioServicio;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Support\Money;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRealMetricsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_historial_facturas_busca_filtra_y_exporta_datos_reales(): void
    {
        [$branch, $admin, $cesarEmployee] = $this->prepareAdminMetricsScenario();

        $cesarInvoice = $this->emitInvoiceForUser($cesarEmployee->usuario, $branch, Servicio::query()->orderBy('id')->firstOrFail(), 26500000);
        $adminInvoice = $this->emitInvoiceForUser($admin, $branch, Servicio::query()->orderBy('id')->skip(1)->firstOrFail(), 16500000);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.index', ['q' => 'cesar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stitch/Frame')
                ->where('invoiceHistory.invoices.0.number', $cesarInvoice->number)
                ->where('invoiceHistory.invoices.0.operator_name', 'Cesar')
                ->where('invoiceHistory.filters.query', 'cesar'));

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.index', ['employee' => $cesarEmployee->public_id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stitch/Frame')
                ->where('invoiceHistory.invoices.0.number', $cesarInvoice->number)
                ->where('invoiceHistory.filters.employeePublicId', $cesarEmployee->public_id));

        $csv = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.export', ['employee' => $cesarEmployee->public_id]));
        $csvContent = $csv->streamedContent();

        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($cesarInvoice->number, $csvContent);
        $this->assertStringContainsString('Cesar', $csvContent);
        $this->assertStringNotContainsString($adminInvoice->number, $csvContent);
    }

    public function test_reportes_analytics_coinciden_con_facturas_reales(): void
    {
        [$branch, $admin, $cesarEmployee] = $this->prepareAdminMetricsScenario();

        $cesarInvoice = $this->emitInvoiceForUser($cesarEmployee->usuario, $branch, Servicio::query()->orderBy('id')->firstOrFail(), 26500000);
        $adminInvoice = $this->emitInvoiceForUser($admin, $branch, Servicio::query()->orderBy('id')->skip(1)->firstOrFail(), 16500000);

        $expectedRevenue = Money::format((int) ($cesarInvoice->total_amount + $adminInvoice->total_amount));
        $expectedTicket = Money::format((int) round(($cesarInvoice->total_amount + $adminInvoice->total_amount) / 2));

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reportes.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ReportsAnalytics')
                ->where('calendarOptions.0.key', '2026-04')
                ->where('datasets.2026-04.summary.ingresos', $expectedRevenue)
                ->where('datasets.2026-04.summary.ticketPromedio', $expectedTicket)
                ->where('datasets.2026-04.summary.servicios', '2')
                ->where('datasets.2026-04.staffPerformance.0.name', 'Cesar')
                ->where('datasets.2026-04.staffPerformance.0.revenue', Money::format((int) $cesarInvoice->total_amount)));

    }

    public function test_rendimiento_ganancias_e_historial_del_empleado_usan_datos_reales(): void
    {
        [$branch, $admin, $cesarEmployee, $empleadoSinFacturas] = $this->prepareAdminMetricsScenario(withEmptyEmployee: true);

        $cesarInvoice = $this->emitInvoiceForUser($cesarEmployee->usuario, $branch, Servicio::query()->orderBy('id')->firstOrFail(), 26500000);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.rendimiento', $cesarEmployee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeePerformance')
                ->where('employee.name', 'Cesar')
                ->where('employee.metrics.totalRevenue', Money::format((int) $cesarInvoice->total_amount))
                ->where('employee.metrics.averageTicket', Money::format((int) $cesarInvoice->total_amount))
                ->where('employee.metrics.invoiceCount', '1')
                ->where('employee.history.0.folio', $cesarInvoice->number));

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.ganancias', $cesarEmployee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeeEarnings')
                ->where('employee.metrics.totalRevenue', Money::format((int) $cesarInvoice->total_amount))
                ->where('employee.earningsBreakdown.0.value', Money::format((int) $cesarInvoice->total_amount)));

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.historial', $cesarEmployee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeeHistory')
                ->where('employee.history.0.folio', $cesarInvoice->number)
                ->where('employee.history.0.paymentMethod', 'Tarjeta manual'));

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.rendimiento', $empleadoSinFacturas))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeePerformance')
                ->where('employee.name', 'Sin Ventas')
                ->where('employee.metrics.totalRevenue', Money::format(0))
                ->where('employee.metrics.invoiceCount', '0')
                ->where('employee.appointments', [])
                ->where('employee.history', []));
    }

    private function prepareAdminMetricsScenario(bool $withEmptyEmployee = false): array
    {
        $this->travelTo(now('America/Tegucigalpa')->setDate(2026, 4, 22)->setTime(12, 22));

        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $cesarEmployee = $this->createEmployeeUser($branch, 'Cesar', 'Artista de Uñas');

        if (! $withEmptyEmployee) {
            return [$branch, $admin, $cesarEmployee];
        }

        $emptyEmployee = $this->createEmployeeUser($branch, 'Sin Ventas', 'Artista de Uñas');

        return [$branch, $admin, $cesarEmployee, $emptyEmployee];
    }

    private function createEmployeeUser(Sucursal $branch, string $name, string $role): Empleado
    {
        $user = $this->createUserWithRole('tecnica', $branch);
        $slug = Str::slug($name);

        $user->update([
            'name' => $name,
            'email' => $slug.'@ferlemnails.local',
        ]);

        $employee = Empleado::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'role_title' => $role,
            'hire_date' => '2026-04-01',
            'is_active' => true,
        ]);

        $employee->sucursales()->syncWithoutDetaching([$branch->id => ['is_primary' => true, 'role_title' => $role]]);

        return $employee->fresh(['usuario']);
    }

    private function emitInvoiceForUser(User $user, Sucursal $branch, Servicio $service, int $priceAmount): Factura
    {
        $this->updateServicePriceForBranch($service, $branch, $priceAmount);

        SesionCaja::firstOrCreate(
            [
                'sucursal_id' => $branch->id,
                'user_id' => $user->id,
                'status' => 'abierta',
            ],
            [
                'opening_amount' => 100000,
                'expected_amount' => 100000,
                'opened_at' => now(),
            ],
        );

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

        return Factura::query()->latest('id')->firstOrFail();
    }

    private function updateServicePriceForBranch(Servicio $service, Sucursal $branch, int $priceAmount): void
    {
        $service->update(['base_price_amount' => $priceAmount]);

        PrecioServicio::query()
            ->where('servicio_id', $service->id)
            ->where('sucursal_id', $branch->id)
            ->update([
                'amount' => $priceAmount,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'is_active' => true,
            ]);

        $service->refresh();
    }
}
