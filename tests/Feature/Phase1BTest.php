<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_renders_the_expected_inertia_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_active_employee_can_login_and_is_redirected_to_new_sale(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('secure-password'),
        ]);
        $user->assignRole('employee');

        $this->post('/login', ['email' => $user->email, 'password' => 'secure-password'])
            ->assertRedirect(route('sales.create'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_employee_is_redirected_away_from_the_owner_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('employee');

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('sales.create'));
    }

    public function test_create_owner_command_is_idempotent_and_assigns_owner_role(): void
    {
        $options = [
            '--name' => 'Propietario de prueba',
            '--email' => 'owner-test@example.com',
            '--password' => 'initial-password',
            '--force' => true,
        ];

        $this->artisan('studio:create-owner', $options)->assertSuccessful();
        $this->artisan('studio:create-owner', $options)->assertSuccessful();

        $owner = User::where('email', 'owner-test@example.com')->firstOrFail();
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertSame(1, User::where('email', 'owner-test@example.com')->count());
    }

    public function test_existing_owner_password_is_preserved_without_force(): void
    {
        $owner = User::factory()->create([
            'email' => 'existing-owner@example.com',
            'password' => Hash::make('original-password'),
            'is_active' => false,
        ]);
        $owner->assignRole('employee');

        $this->artisan('studio:create-owner', [
            '--name' => 'Propietario actualizado',
            '--email' => $owner->email,
            '--password' => 'replacement-password',
        ])->assertSuccessful();

        $owner->refresh();
        $this->assertSame('Propietario actualizado', $owner->name);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue(Hash::check('original-password', $owner->password));
    }
}
