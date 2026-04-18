<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Empleados\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_editar_y_eliminar_empleado_con_login_real(): void
    {
        $branch = $this->seedBaseData();
        $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local'))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('empleados.admin.store'), [
                'name' => 'Elena Rossi',
                'role' => 'Técnica de Pestañas',
                'email' => 'elena@ferlemnails.local',
                'startDate' => '2026-04-01',
                'password' => '1234',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'elena@ferlemnails.local')->firstOrFail();
        $employee = Empleado::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertTrue($user->hasRole('tecnica'));
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertTrue($user->is_active);
        $this->assertTrue($employee->is_active);

        $this->post(route('logout'))->assertRedirect('/login');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Elena Rossi');

        $this->post(route('login.store'), [
            'profile_public_id' => $user->public_id,
            'password' => '1234',
        ])->assertRedirect('/inicio-de-cobro');

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('empleados.admin.update', $employee), [
                'name' => 'Elena Rossi Editada',
                'role' => 'Artista de Uñas',
                'email' => 'elena.rossi@ferlemnails.local',
                'startDate' => '2026-04-03',
                'password' => '5678',
            ])
            ->assertRedirect();

        $user->refresh();
        $employee->refresh();

        $this->assertSame('Elena Rossi Editada', $user->name);
        $this->assertSame('elena.rossi@ferlemnails.local', $user->email);
        $this->assertSame('Artista de Uñas', $employee->role_title);

        $this->post(route('logout'))->assertRedirect('/login');

        $this->post(route('login.store'), [
            'profile_public_id' => $user->public_id,
            'password' => '5678',
        ])->assertRedirect('/inicio-de-cobro');

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->delete(route('empleados.admin.destroy', $employee))
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->is_active);

        $this->post(route('logout'))->assertRedirect('/login');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Elena Rossi Editada');
    }
}
