<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_asigna_sucursal_activa_y_registra_ultimo_acceso(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('admin_negocio', $branch);

        $response = $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($branch->id, session('active_branch_id'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_redirige_a_selector_si_tiene_varias_sucursales_sin_predeterminada(): void
    {
        $branch = $this->seedBaseData();
        $otherBranch = $this->createBranch('SN-10', 'Sucursal Norte');
        $user = $this->createUserWithRole('cajero', $branch);

        $user->sucursales()->sync([
            $branch->id => ['is_default' => false],
            $otherBranch->id => ['is_default' => false],
        ]);

        $response = $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('sucursales.selector'));
        $this->assertNull(session('active_branch_id'));
    }
}
