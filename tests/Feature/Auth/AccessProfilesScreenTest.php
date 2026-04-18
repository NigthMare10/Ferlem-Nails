<?php

namespace Tests\Feature\Auth;

use App\Modules\Empleados\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessProfilesScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_perfiles_visibles_de_acceso(): void
    {
        $this->seedBaseData();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Administracion General');
        $response->assertSee('Prueba');
        $response->assertSee('FERLEM NAILS');
    }

    public function test_no_muestra_perfiles_inactivos_o_sin_sucursal(): void
    {
        $branch = $this->seedBaseData();
        $activeUser = $this->createUserWithRole('cajero', $branch);
        $inactiveUser = $this->createUserWithRole('recepcionista', $branch);
        $inactiveUser->update(['is_active' => false]);

        $orphanUser = $this->createUserWithRole('tecnica');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee($activeUser->name);
        $response->assertDontSee($inactiveUser->name);
        $response->assertDontSee($orphanUser->name);
    }

    public function test_no_muestra_usuario_cuyo_empleado_esta_inactivo(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('tecnica', $branch);

        Empleado::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_title' => 'Tecnica en pausa',
            'is_active' => false,
        ]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee($user->name);
    }
}
