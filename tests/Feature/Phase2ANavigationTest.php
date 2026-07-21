<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2ANavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_owner_starts_on_home_with_sales_navigation(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('auth.user.name', $owner->name)
                ->missing('auth.user.data')
                ->where('auth.navigation.home', true)
                ->where('auth.navigation.sales', true)
                ->missing('auth.navigation.cash'));
    }

    public function test_guests_are_redirected_from_protected_landings(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/sales/new')->assertRedirect(route('login'));
        $this->get('/cash')->assertRedirect(route('login'));
    }

    public function test_owner_old_cash_url_redirects_to_sales_without_cycle(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->get('/cash')->assertRedirect(route('sales.create'));
        $this->get('/sales/new')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Sales/Create'));
    }

    public function test_administrator_starts_on_sales_then_uses_first_permitted_configuration_screen(): void
    {
        $administrator = $this->user('administrator');

        $this->actingAs($administrator)->get('/')->assertRedirect(route('sales.create'));

        Role::findByName('administrator')->syncPermissions([
            Permissions::SETTINGS_ACCESS,
            Permissions::SERVICES_VIEW,
        ]);

        $this->actingAs($administrator)->get('/')->assertRedirect(route('configuration.services.index'));
    }

    public function test_administrator_without_any_destination_receives_forbidden(): void
    {
        $administrator = $this->user('administrator');
        Role::findByName('administrator')->syncPermissions([]);

        $this->actingAs($administrator)->get('/')->assertForbidden();
        $this->get('/sales/new')->assertForbidden();
    }

    public function test_employee_uses_new_sale_instead_of_home_or_cash(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/')->assertRedirect(route('sales.create'));
        $this->get('/cash')->assertRedirect(route('sales.create'));
        $this->get('/sales/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Create')
                ->where('auth.navigation.home', false)
                ->where('auth.navigation.sales', true)
                ->missing('auth.navigation.cash'));
    }

    public function test_login_discards_unauthorized_intended_destination_and_uses_sales(): void
    {
        $employee = $this->user('employee', ['password' => Hash::make('secure-password')]);

        $this->withSession(['url.intended' => url('/configuration/users')])
            ->post('/login', [
                'email' => $employee->email,
                'password' => 'secure-password',
            ])
            ->assertRedirect(route('sales.create'));
    }

    public function test_sales_requires_an_authorized_user(): void
    {
        $userWithoutRole = User::factory()->create(['is_active' => true]);

        $this->actingAs($userWithoutRole)->get('/sales/new')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/Forbidden'));
    }

    public function test_employee_cannot_enter_configuration(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/configuration')->assertForbidden();
        $this->get('/configuration/users')->assertForbidden();
        $this->get('/configuration/services')->assertForbidden();
    }

    public function test_inactive_state_is_persisted_and_blocks_sales(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->post('/configuration/users', [
            'name' => 'Empleado inactivo',
            'email' => 'inactive@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employee',
            'is_active' => false,
        ])->assertSessionHas('success');

        $employee = User::where('email', 'inactive@example.com')->firstOrFail();
        $this->assertFalse($employee->is_active);

        $this->actingAs($owner)->patch("/configuration/users/{$employee->id}/status", ['is_active' => true]);
        $this->assertTrue($employee->fresh()->is_active);
        $this->patch("/configuration/users/{$employee->id}/status", ['is_active' => false]);
        $this->assertFalse($employee->fresh()->is_active);

        $this->actingAs($employee->fresh())->get('/sales/new')->assertRedirect(route('login'));
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }
}
