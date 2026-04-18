<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalogo\Models\CategoriaServicio;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_ver_precio_configuracion_y_rendimiento_especifico(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $employee = Empleado::query()
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $branch->id))
            ->orderBy('name')
            ->firstOrFail();

        $priceResponse = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('catalogo.index'));

        $priceResponse->assertOk();
        $priceResponse->assertInertia(fn ($page) => $page
            ->component('Admin/PriceSettings')
            ->where('title', 'Ajuste de Precios Admin'));

        $settingsResponse = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('admin.settings'));

        $settingsResponse->assertOk();
        $settingsResponse->assertInertia(fn ($page) => $page
            ->component('Admin/AdminSettings')
            ->where('branch.currencyCode', 'HNL'));

        $performanceResponse = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.rendimiento', $employee));

        $performanceResponse->assertOk();
        $performanceResponse->assertInertia(fn ($page) => $page
            ->component('Admin/EmployeePerformance')
            ->where('employee.name', $employee->name));

        $earningsResponse = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.ganancias', $employee));

        $earningsResponse->assertOk();
        $earningsResponse->assertInertia(fn ($page) => $page
            ->component('Admin/EmployeeEarnings')
            ->where('employee.name', $employee->name));

        $historyResponse = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.historial', $employee));

        $historyResponse->assertOk();
        $historyResponse->assertInertia(fn ($page) => $page
            ->component('Admin/EmployeeHistory')
            ->where('employee.name', $employee->name));
    }

    public function test_admin_puede_exportar_reporte_de_empleado(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $employee = Empleado::query()
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $branch->id))
            ->orderBy('name')
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.exportar', $employee));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($employee->name, $response->streamedContent());
        $this->assertStringContainsString('Ingresos totales', $response->streamedContent());
    }

    public function test_admin_puede_crear_y_editar_servicio_desde_ajuste_de_precios(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $category = CategoriaServicio::query()->orderBy('name')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('catalogo.admin.store'), [
                'name' => 'Diseño Editorial',
                'description' => 'Aplicación artística premium para validación.',
                'durationMinutes' => 75,
                'price' => '650.00',
                'categoryPublicId' => $category->public_id,
            ])
            ->assertRedirect();

        $service = Servicio::query()->where('name', 'Diseño Editorial')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('catalogo.admin.update', $service), [
                'name' => 'Diseño Editorial Premium',
                'description' => 'Aplicación artística premium actualizada.',
                'durationMinutes' => 90,
                'price' => '725.00',
                'categoryPublicId' => $category->public_id,
            ])
            ->assertRedirect();

        $service->refresh();

        $this->assertSame('Diseño Editorial Premium', $service->name);
        $this->assertSame(90, $service->duration_minutes);
        $this->assertSame(72500, $service->base_price_amount);
    }
}
