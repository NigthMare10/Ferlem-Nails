<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSwitchLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_cambiar_de_admin_a_prueba_sin_arrastrar_la_sesion_anterior(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();
        $prueba = User::query()->where('email', 'prueba@ferlemnails.local')->firstOrFail();

        $admin->update(['password' => Hash::make('password')]);
        $prueba->update(['password' => Hash::make('password')]);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('login.store'), [
                'profile_public_id' => $prueba->public_id,
                'password' => 'password',
            ])
            ->assertRedirect(route('pos.index', absolute: false));

        $this->assertAuthenticatedAs($prueba);
    }
}
