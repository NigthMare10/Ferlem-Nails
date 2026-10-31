<?php

namespace Tests\Feature;

use App\Actions\Appointments\BuildAppointmentAvailabilityAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Loyalty\LoyaltyService;
use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\EmployeeBreak;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyReward;
use App\Models\Service;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use App\Support\AppointmentRevision;
use App\Support\EmployeeAvailability;
use App\Support\ItemDiscount;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExtendedOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(string $role = 'owner'): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role);

        return $u;
    }

    private function member(): Customer
    {
        $c = Customer::create(['name' => 'Clienta privada', 'email' => strtolower(Str::random(8)).'@example.test', 'password' => 'Testing12345']);
        $c->forceFill(['email_verified_at' => now()])->save();

        return $c;
    }

    private function service(): Service
    {
        return Service::create(['name' => 'Manicura', 'price' => '700.00', 'duration_minutes' => 60, 'is_active' => true, 'loyalty_points' => 20]);
    }

    public function test_customer_session_cannot_access_administration(): void
    {
        $c = $this->member();
        $this->actingAs($c, 'customer')->get('/clientes')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Customer/Portal')->where('auth', null));
        $this->get('/sales/new')->assertRedirect('/login');
        $this->get('/configuration/users')->assertRedirect('/login');
    }

    public function test_registration_never_creates_staff_or_accepts_privileges(): void
    {
        Notification::fake();
        $this->post('/clientes/registro', ['name' => 'Nueva', 'email' => 'NEW@example.test', 'password' => 'Testing12345', 'password_confirmation' => 'Testing12345', 'role' => 'owner', 'is_active' => false])->assertRedirect('/clientes');
        $this->assertDatabaseHas('customers', ['email' => 'new@example.test', 'is_active' => true]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_portal_queries_only_current_customer(): void
    {
        $a = $this->member();
        $b = $this->member();
        LoyaltyEntry::create(['customer_id' => $b->id, 'type' => 'adjustment', 'points' => 999, 'source_key' => 'private', 'note' => 'SECRET']);
        $this->actingAs($a, 'customer')->get('/clientes?customer_id='.$b->id)->assertInertia(fn (Assert $p) => $p->where('balance', 0)->has('entries.data', 0));
    }

    public function test_employee_cannot_grant_permissions_to_self(): void
    {
        $u = $this->staff('employee');
        $u->givePermissionTo(['settings.access', 'users.update']);
        $this->actingAs($u)->put('/configuration/users/'.$u->id.'/permissions', ['permissions' => ['sales.checkout_any']])->assertForbidden();
    }

    public function test_owner_can_grant_individual_permission_without_changing_role(): void
    {
        $owner = $this->staff();
        $employee = $this->staff('employee');
        $this->actingAs($owner)->put('/configuration/users/'.$employee->id.'/permissions', ['permissions' => ['sales.checkout_any']])->assertSessionHasNoErrors();
        $this->assertTrue($employee->fresh()->can('sales.checkout_any'));
        $this->assertTrue($employee->fresh()->hasRole('employee'));
        $this->assertFalse($employee->fresh()->can('reports.sales.view'));
    }

    public function test_break_overlap_boundaries_are_half_open(): void
    {
        $u = $this->staff('employee');
        $day = CarbonImmutable::parse('2030-01-07 12:00', 'America/Tegucigalpa');
        EmployeeBreak::create(['user_id' => $u->id, 'weekday' => $day->dayOfWeek, 'starts_at' => '12:00:00', 'ends_at' => '13:00:00']);
        $this->assertFalse(EmployeeAvailability::hasBreak($u->id, $day->subHour(), $day));
        $this->assertTrue(EmployeeAvailability::hasBreak($u->id, $day, $day->addMinutes(30)));
        $this->assertFalse(EmployeeAvailability::hasBreak($u->id, $day->addHour(), $day->addHours(2)));
    }

    public function test_individual_discount_is_authoritative_and_persisted(): void
    {
        $u = $this->staff();
        $s = $this->service();
        $sale = app(CreateSaleAction::class)->execute($u, [['service_id' => $s->id, 'quantity' => 2, 'discount_type' => 'percent', 'discount_value' => '10', 'discount_reason' => 'Cortesía autorizada']], (string) Str::uuid(), 'cash');
        $this->assertSame('1260.00', $sale->total);
        $this->assertSame('140.00', $sale->items->first()->item_discount_amount);
        $this->assertSame(40, $sale->items->first()->loyalty_points);
    }

    public function test_discount_requires_permission(): void
    {
        $u = $this->staff('employee');
        $s = $this->service();
        $this->actingAs($u)->post('/sales', ['checkout_token' => (string) Str::uuid(), 'payment_method' => 'cash', 'items' => [['service_id' => $s->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '10', 'discount_reason' => 'Sin permiso']]])->assertForbidden();
    }

    public function test_discounts_cannot_exceed_line_or_stack(): void
    {
        $u = $this->staff();
        $line = ['unit_price_cents' => 10000, 'quantity' => 1, 'line_total_cents' => 10000];
        foreach ([['discount_type' => 'percent', 'discount_value' => '101', 'discount_reason' => 'Prueba'], ['discount_type' => 'fixed', 'discount_value' => '101', 'discount_reason' => 'Prueba']] as $input) {
            try {
                ItemDiscount::apply($u, $line, $input);
                $this->fail('Expected invalid discount');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_qr_award_is_unique_and_cancellation_reverses_it(): void
    {
        $u = $this->staff();
        $s = $this->service();
        $c = $this->member();
        $sale = app(CreateSaleAction::class)->execute($u, [['service_id' => $s->id, 'quantity' => 1]], (string) Str::uuid(), 'cash');
        foreach (['one', 'two'] as $key) {
            $token = str_repeat($key === 'one' ? 'a' : 'b', 64);
            DB::table('customer_qr_tokens')->insert(['customer_id' => $c->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(5)]);
            app(LoyaltyService::class)->award($u, $sale, $token);
        }
        $this->assertSame(20, app(LoyaltyService::class)->balance($c));
        $this->assertDatabaseCount('loyalty_entries', 1);
        app(CancelSaleAction::class)->execute($u, $sale, 'Prueba de devolución');
        $this->assertSame(0, app(LoyaltyService::class)->balance($c));
    }

    public function test_redemption_is_idempotent_and_cancel_restores_stock_and_points_once(): void
    {
        $c = $this->member();
        LoyaltyEntry::create(['customer_id' => $c->id, 'type' => 'adjustment', 'points' => 100, 'source_key' => 'opening']);
        $r = LoyaltyReward::create(['name' => 'Diseño gratis', 'points_cost' => 60, 'stock' => 1, 'per_customer_limit' => 1, 'is_active' => true]);
        $service = app(LoyaltyService::class);
        $token = (string) Str::uuid();
        $one = $service->reserve($c, $r, $token);
        $two = $service->reserve($c, $r, $token);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(40, $service->balance($c));
        $service->release($one);
        $service->release($one);
        $this->assertSame(100, $service->balance($c));
        $this->assertSame(1, $r->fresh()->stock);
    }

    public function test_unverified_member_cannot_generate_qr_or_redeem(): void
    {
        $c = $this->member();
        $c->forceFill(['email_verified_at' => null])->save();
        $r = LoyaltyReward::create(['name' => 'Gift', 'points_cost' => 1, 'is_active' => true]);
        $this->actingAs($c, 'customer')->postJson('/clientes/qr')->assertForbidden();
        $this->post('/clientes/recompensas/'.$r->id.'/canjes', ['operation_token' => (string) Str::uuid()])->assertForbidden();
    }

    private function appointment(User $actor, User $performer, Service $service): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($actor, ['client_name' => 'Prueba de edición', 'date' => '2030-01-07', 'start_time' => '10:00', 'items' => [['service_id' => $service->id, 'assigned_to' => $performer->id, 'quantity' => 1, 'duration_minutes' => 60]], 'notes' => null]);
    }

    public function test_edit_services_preserves_existing_price_and_uses_current_price_for_new_lines(): void
    {
        $owner = $this->staff();
        $employee = $this->staff('employee');
        $service = $this->service();
        $appointment = $this->appointment($owner, $employee, $service);
        $item = $appointment->items->first();
        $service->update(['price' => '900.00']);
        $data = ['client_name' => $appointment->client_name, 'date' => '2030-01-07', 'start_time' => '10:00', 'expected_updated_at' => $appointment->updated_at->toISOString(), 'expected_revision' => AppointmentRevision::of($appointment), 'items' => [['id' => $item->id, 'service_id' => $service->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 30], ['service_id' => $service->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 30]]];
        $this->actingAs($owner)->put('/appointments/'.$appointment->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('1600.00', $appointment->fresh()->expected_total);
        $this->assertDatabaseCount('appointment_items', 2);
    }

    public function test_edit_rejects_stale_revision_without_partial_changes(): void
    {
        $owner = $this->staff();
        $e = $this->staff('employee');
        $s = $this->service();
        $a = $this->appointment($owner, $e, $s);
        $i = $a->items->first();
        $data = ['client_name' => 'Cambio', 'date' => '2030-01-07', 'start_time' => '10:00', 'expected_updated_at' => $a->updated_at->toISOString(), 'expected_revision' => str_repeat('0', 64), 'items' => [['id' => $i->id, 'service_id' => $s->id, 'assigned_to' => $e->id, 'quantity' => 1, 'duration_minutes' => 60]]];
        $this->actingAs($owner)->put('/appointments/'.$a->id, $data)->assertSessionHasErrors('appointment');
        $this->assertSame('Prueba de edición', $a->fresh()->client_name);
    }

    public function test_meal_blocks_creation_and_is_removed_from_availability(): void
    {
        $owner = $this->staff();
        $e = $this->staff('employee');
        $s = $this->service();
        EmployeeBreak::create(['user_id' => $e->id, 'weekday' => 1, 'starts_at' => '12:00:00', 'ends_at' => '13:00:00']);
        $items = [['service_id' => $s->id, 'assigned_to' => $e->id, 'quantity' => 1, 'duration_minutes' => 60]];
        $this->actingAs($owner)->post('/appointments', ['client_name' => 'Comida', 'date' => '2030-01-07', 'start_time' => '12:00', 'items' => $items])->assertSessionHasErrors('start_time');
        $result = app(BuildAppointmentAvailabilityAction::class)->execute($owner, '2030-01-07', $items);
        $this->assertNotContains('12:00', $result['available_times']);
        $this->assertContains('13:00', $result['available_times']);
    }

    public function test_same_service_can_have_distinct_line_discounts(): void
    {
        $owner = $this->staff();
        $s = $this->service();
        $sale = app(CreateSaleAction::class)->execute($owner, [['service_id' => $s->id, 'quantity' => 1], ['service_id' => $s->id, 'quantity' => 1, 'discount_type' => 'fixed', 'discount_value' => '100', 'discount_reason' => 'Cortesía']], (string) Str::uuid(), 'card');
        $this->assertCount(2, $sale->items);
        $this->assertSame('1300.00', $sale->total);
        $this->assertSame('52.00', $sale->card_fee_amount);
    }

    public function test_insufficient_points_never_decrements_stock(): void
    {
        $c = $this->member();
        $r = LoyaltyReward::create(['name' => 'Gift', 'points_cost' => 100, 'stock' => 1, 'is_active' => true]);
        try {
            app(LoyaltyService::class)->reserve($c, $r, (string) Str::uuid());
            $this->fail('Expected insufficient balance');
        } catch (ValidationException) {
            $this->assertSame(1, $r->fresh()->stock);
            $this->assertDatabaseCount('loyalty_redemptions', 0);
        }
    }

    public function test_password_recovery_does_not_modify_employee_with_same_email(): void
    {
        Notification::fake();
        $c = $this->member();
        $staff = $this->staff();
        $staff->update(['email' => $c->email]);
        $original = $staff->password;
        $oldCustomerPassword = $c->password;
        $this->post('/clientes/olvide-contrasena', ['email' => $c->email, 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertSessionHas('success');
        $this->assertSame($oldCustomerPassword, $c->fresh()->password);
        $token = null;
        Notification::assertSentTo($c, CustomerResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->post('/clientes/restablecer-contrasena', ['email' => $c->email, 'token' => $token, 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertRedirect('/clientes/login');
        $this->assertTrue(Hash::check('NewPassword12345', $c->fresh()->password));
        $this->assertSame($original, $staff->fresh()->password);
    }

    public function test_expired_qr_cannot_assign_or_link_a_sale(): void
    {
        $u = $this->staff();
        $c = $this->member();
        $s = $this->service();
        $sale = app(CreateSaleAction::class)->execute($u, [['service_id' => $s->id, 'quantity' => 1]], (string) Str::uuid(), 'cash');
        $token = str_repeat('e', 64);
        DB::table('customer_qr_tokens')->insert(['customer_id' => $c->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute()]);
        $this->actingAs($u)->post('/sales/'.$sale->id.'/loyalty-awards', ['token' => $token])->assertSessionHasErrors('qr');
        $this->assertDatabaseCount('loyalty_entries', 0);
        $this->assertNull($sale->fresh()->customer_id);
    }

    public function test_linked_appointment_is_private_and_awards_only_when_checked_out(): void
    {
        $u = $this->staff();
        $e = $this->staff('employee');
        $c = $this->member();
        $s = $this->service();
        $a = $this->appointment($u, $e, $s);
        $token = str_repeat('l', 64);
        DB::table('customer_qr_tokens')->insert(['customer_id' => $c->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinute()]);
        $this->actingAs($u)->post('/appointments/'.$a->id.'/customer', ['token' => $token])->assertSessionHas('success');
        $this->assertSame(0, app(LoyaltyService::class)->balance($c));
        $items = $a->items->map(fn ($i) => ['appointment_item_id' => $i->id, 'service_id' => $s->id, 'quantity' => 1, 'performed_by' => $e->id])->all();
        $this->post('/appointments/'.$a->id.'/checkout', ['items' => $items, 'removed_appointment_item_ids' => [], 'checkout_token' => (string) Str::uuid(), 'payment_method' => 'cash'])->assertStatus(303);
        $this->assertSame(20, app(LoyaltyService::class)->balance($c));
        $other = $this->member();
        $this->actingAs($other, 'customer')->get('/clientes/citas')->assertRedirect('/clientes');
        $this->get('/clientes')->assertSuccessful();
        $this->get('/clientes', ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(), 'X-Inertia-Partial-Component' => 'Customer/Portal', 'X-Inertia-Partial-Data' => 'appointments'])->assertOk()->assertJsonCount(0, 'props.appointments');
    }

    public function test_free_line_does_not_generate_points(): void
    {
        $u = $this->staff();
        $s = $this->service();
        $sale = app(CreateSaleAction::class)->execute($u, [['service_id' => $s->id, 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => '100', 'discount_reason' => 'Regalo autorizado']], (string) Str::uuid(), 'cash');
        $this->assertSame('0.00', $sale->total);
        $this->assertSame(0, $sale->items->first()->loyalty_points);
    }

    public function test_private_photo_has_no_cross_customer_identifier(): void
    {
        Storage::fake('local');
        $a = $this->member();
        $b = $this->member();
        Storage::disk('local')->put('customer-avatars/private.webp', 'private-image');
        $a->forceFill(['avatar_path' => 'customer-avatars/private.webp'])->save();
        $this->actingAs($b, 'customer')->get('/clientes/foto?customer_id='.$a->id)->assertNotFound();
        $this->actingAs($a, 'customer')->withSession(['customer_password_signature' => hash('sha256', $a->password)])->get('/clientes/foto')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_password_change_invalidates_customer_session(): void
    {
        $c = $this->member();
        $this->actingAs($c, 'customer')->get('/clientes')->assertOk();
        $c->update(['password' => 'DifferentPassword123']);
        $this->get('/clientes')->assertRedirect('/clientes/login');
    }

    public function test_changed_catalog_price_requires_a_new_confirmation(): void
    {
        $u = $this->staff();
        $s = $this->service();
        $this->actingAs($u)->post('/sales', ['checkout_token' => (string) Str::uuid(), 'payment_method' => 'cash', 'items' => [['service_id' => $s->id, 'quantity' => 1, 'expected_price' => '1.00']]])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_break_configuration_rejects_overlap_and_existing_booking_without_partial_changes(): void
    {
        $u = $this->staff();
        $e = $this->staff('employee');
        $s = $this->service();
        $this->appointment($u, $e, $s);
        $this->actingAs($u)->put('/configuration/users/'.$e->id.'/breaks', ['display_order' => 2, 'breaks' => [['weekday' => 1, 'starts_at' => '10:30', 'ends_at' => '11:30']]])->assertSessionHasErrors('breaks');
        $this->assertDatabaseCount('employee_breaks', 0);
        $this->put('/configuration/users/'.$e->id.'/breaks', ['display_order' => 2, 'breaks' => [['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '13:00'], ['weekday' => 1, 'starts_at' => '12:30', 'ends_at' => '13:30']]])->assertSessionHasErrors('breaks');
        $this->assertDatabaseCount('employee_breaks', 0);
    }

    public function test_signed_email_verification_cannot_verify_a_different_customer(): void
    {
        $a = $this->member();
        $b = $this->member();
        $a->forceFill(['email_verified_at' => null])->save();
        $b->forceFill(['email_verified_at' => null])->save();
        $foreign = URL::temporarySignedRoute('customer.verification.verify', now()->addMinutes(5), ['id' => $b->id, 'hash' => sha1($b->email)]);
        $this->actingAs($a, 'customer')->get($foreign)->assertForbidden();
        $this->assertNull($b->fresh()->email_verified_at);
        $own = URL::temporarySignedRoute('customer.verification.verify', now()->addMinutes(5), ['id' => $a->id, 'hash' => sha1($a->email)]);
        $this->get($own)->assertRedirect('/clientes');
        $this->assertNotNull($a->fresh()->email_verified_at);
    }

    public function test_avatar_upload_is_reencoded_and_stored_privately(): void
    {
        Storage::fake('local');
        $customer = $this->member();
        $photo = UploadedFile::fake()->image('profile.jpg', 200, 200);
        $this->actingAs($customer, 'customer')->post('/clientes/foto', ['photo' => $photo])->assertSessionHas('success');
        $path = $customer->fresh()->avatar_path;
        $this->assertStringStartsWith('customer-avatars/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringStartsWith('RIFF', Storage::disk('local')->get($path));
        $this->get('/clientes/foto')->assertHeader('Content-Type', 'image/webp');
    }
}
