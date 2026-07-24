<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConfigurationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role = 'employee', bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_settings_access_cannot_enter_configuration(): void
    {
        $this->actingAs($this->user())->get('/configuration')->assertForbidden();
    }

    public function test_user_without_users_view_cannot_list_users(): void
    {
        $this->actingAs($this->user())->get('/configuration/users')->assertForbidden();
    }

    public function test_user_without_services_view_cannot_list_services(): void
    {
        $this->actingAs($this->user())->get('/configuration/services')->assertForbidden();
    }

    public function test_hidden_frontend_button_cannot_bypass_backend_authorization(): void
    {
        $this->actingAs($this->user())->post('/configuration/services', ['name' => 'Gel', 'duration_minutes' => 30, 'price' => 100, 'is_active' => true])->assertForbidden();
    }

    public function test_owner_can_create_users(): void
    {
        $this->actingAs($this->user('owner'))->post('/configuration/users', ['name' => 'Nueva', 'email' => 'nueva@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'employee', 'is_active' => true])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'nueva@example.com']);
    }

    public function test_administrator_can_create_normal_users_but_not_owners(): void
    {
        $admin = $this->user('administrator');
        $this->actingAs($admin)->post('/configuration/users', ['name' => 'Normal', 'email' => 'normal@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'employee', 'is_active' => true])->assertSessionHas('success');
        $this->actingAs($admin)->post('/configuration/users', ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'owner', 'is_active' => true])->assertForbidden();
    }

    public function test_administrator_cannot_edit_or_deactivate_owner(): void
    {
        $owner = $this->user('owner');
        $admin = $this->user('administrator');
        $this->actingAs($admin)->put("/configuration/users/{$owner->id}", ['name' => 'No', 'email' => $owner->email])->assertForbidden();
        $this->actingAs($admin)->patch("/configuration/users/{$owner->id}/status", ['is_active' => false])->assertForbidden();
    }

    public function test_last_owner_cannot_be_deactivated_or_lose_role(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->patch("/configuration/users/{$owner->id}/status", ['is_active' => false])->assertStatus(422);
        $this->actingAs($owner)->put("/configuration/users/{$owner->id}", ['name' => $owner->name, 'email' => $owner->email, 'role' => 'administrator'])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->user('employee', false);
        $user->update(['password' => Hash::make('password123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_with_services_create_can_create_a_service(): void
    {
        $user = $this->user();
        $user->givePermissionTo('settings.access', 'services.create');
        $this->actingAs($user)->post('/configuration/services', ['name' => 'Gel', 'duration_minutes' => 45, 'price' => 250, 'is_active' => true])->assertSessionHas('success');
    }

    public function test_service_permissions_and_validations_are_enforced(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->post('/configuration/services', ['name' => 'Gel', 'duration_minutes' => 0, 'price' => -1, 'is_active' => true])->assertSessionHasErrors(['duration_minutes', 'price']);
        $this->actingAs($owner)->post('/configuration/services', ['name' => 'Gel', 'duration_minutes' => 45, 'price' => 250, 'is_active' => true])->assertSessionHas('success');
        $service = Service::first();
        $this->actingAs($owner)->put("/configuration/services/{$service->id}", ['name' => 'Gel premium', 'duration_minutes' => 60, 'price' => 300, 'is_active' => true])->assertSessionHas('success');
        $this->actingAs($owner)->patch("/configuration/services/{$service->id}/status", ['is_active' => false])->assertSessionHas('success');
        $this->actingAs($owner)->delete("/configuration/services/{$service->id}")->assertSessionHas('success');
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', count(Permissions::all()));
    }
}
