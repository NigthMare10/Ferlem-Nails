<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectAfterLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_cajero_redirige_a_pos(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('cajero', $branch);

        $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ])->assertRedirect(route('pos.index', absolute: false));
    }

    public function test_tecnica_redirige_a_inicio_de_cobro(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('tecnica', $branch);

        $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ])->assertRedirect(route('pos.index', absolute: false));
    }

    public function test_recepcionista_redirige_a_inicio_de_cobro(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('recepcionista', $branch);

        $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ])->assertRedirect(route('pos.index', absolute: false));
    }

    public function test_auditor_redirige_a_reportes(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('auditor', $branch);

        $this->post('/login', [
            'profile_public_id' => $user->public_id,
            'password' => 'password',
        ])->assertRedirect(route('reportes.index', absolute: false));
    }
}
