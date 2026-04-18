<?php

namespace Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMatrixAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_recepcionista_puede_entrar_a_inicio_de_cobro_pero_no_al_dashboard_admin(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('recepcionista', $branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('pos.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_auditor_puede_ver_reportes_pero_no_operar_pos(): void
    {
        $branch = $this->seedBaseData();
        $user = $this->createUserWithRole('auditor', $branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reportes.index'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('pos.index'))
            ->assertForbidden();
    }
}
