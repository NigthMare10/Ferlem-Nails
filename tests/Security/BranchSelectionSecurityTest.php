<?php

namespace Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSelectionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_puede_cambiar_a_una_sucursal_no_asignada(): void
    {
        $branchA = $this->seedBaseData();
        $branchB = $this->createBranch('SN-01', 'Sucursal Norte');
        $user = $this->createUserWithRole('cajero', $branchA);

        $response = $this->actingAs($user)
            ->post(route('sucursales.activate'), [
                'sucursal_public_id' => $branchB->public_id,
            ]);

        $response->assertNotFound();
        $this->assertNotSame($branchB->id, session('active_branch_id'));
    }
}
