<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyCashRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cash_table_remains_as_legacy_schema(): void
    {
        $this->assertTrue(Schema::hasTable('cash_sessions'));
        $this->assertTrue(Schema::hasColumn('cash_sessions', 'active_guard'));
    }

    public function test_old_cash_url_redirects_authorized_user_to_new_sale_without_writing_cash(): void
    {
        $employee = $this->user('employee');
        $before = CashSession::query()->count();

        $this->actingAs($employee)->get('/cash')->assertRedirect(route('sales.create'));

        $this->assertSame($before, CashSession::query()->count());
    }

    public function test_cash_opening_and_closing_routes_no_longer_exist(): void
    {
        $employee = $this->user('employee');

        $this->assertFalse(Route::has('cash.open'));
        $this->assertFalse(Route::has('cash.close'));
        $this->actingAs($employee)->post('/cash/open')->assertNotFound();
        $this->post('/cash/close')->assertNotFound();
    }

    public function test_cash_permissions_are_not_assigned_to_operational_roles(): void
    {
        foreach (['owner', 'administrator', 'employee'] as $role) {
            $this->assertSame(0, Role::findByName($role)->permissions()->where('name', 'like', 'cash.%')->count());
        }
    }

    public function test_navigation_exposes_sales_and_not_cash(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/sales/new')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.navigation.sales', true)
                ->missing('auth.navigation.cash'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
