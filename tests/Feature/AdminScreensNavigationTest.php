<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScreensNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_entrar_a_multiples_pantallas_reales_de_stitch(): void
    {
        $branch = $this->seedBaseData();
        $admin = \App\Models\User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Reportes de Ventas')
            ->assertSee('FERLEM NAILS')
            ->assertSee('HNL');

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('empleados.index'))
            ->assertOk()
            ->assertSee('Lista de Empleados');

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('facturas.index'))
            ->assertOk()
            ->assertSee('Historial de Facturas');
    }

    public function test_logout_funciona_y_regresa_a_login(): void
    {
        $branch = $this->seedBaseData();
        $admin = \App\Models\User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('logout'))
            ->assertRedirect('/login');
    }
}
