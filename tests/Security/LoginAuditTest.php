<?php

namespace Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audita_intentos_fallidos_de_inicio_de_sesion(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('admin_negocio', $branch);

        $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('auditoria_eventos', [
            'action' => 'auth.login_failed',
        ]);
    }
}
