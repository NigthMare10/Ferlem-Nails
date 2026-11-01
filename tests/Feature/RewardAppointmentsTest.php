<?php

namespace Tests\Feature;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\MarkAppointmentNoShowAction;
use App\Actions\Appointments\RecordAppointmentDepositAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Actions\Loyalty\LoyaltyService;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyReward;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RewardAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2030-01-07 08:00', 'America/Tegucigalpa'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function data(): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        $employee = User::factory()->create(['is_active' => true]);
        $employee->assignRole('employee');
        $c = Customer::create(['name' => 'Clienta sin correo verificado', 'email' => Str::uuid().'@example.test', 'password' => 'OriginalPassword123', 'phone' => '99990000']);
        $s = Service::create(['name' => 'Manicura premiada', 'price' => '700.00', 'duration_minutes' => 60, 'loyalty_points' => 20, 'is_active' => true]);
        $reward = LoyaltyReward::create(['name' => $s->name, 'service_id' => $s->id, 'benefit_type' => 'service', 'points_cost' => 50, 'stock' => 1, 'per_customer_limit' => 1, 'is_active' => true]);
        LoyaltyEntry::create(['customer_id' => $c->id, 'points' => 100, 'type' => 'adjustment', 'source_key' => (string) Str::uuid()]);
        $r = app(LoyaltyService::class)->reserve($c, $reward, (string) Str::uuid());
        $input = ['client_name' => 'Nombre manipulado', 'date' => '2030-01-07', 'start_time' => '10:00', 'items' => [['service_id' => $s->id, 'assigned_to' => $employee->id, 'quantity' => 1, 'duration_minutes' => 60]]];

        return compact('owner', 'employee', 'c', 's', 'reward', 'r', 'input');
    }

    public function test_registration_is_immediately_functional_without_sending_email(): void
    {
        Notification::fake();
        $this->post('/clientes/registro', ['name' => 'Nueva clienta', 'email' => 'new@example.test', 'password' => 'OriginalPassword123', 'password_confirmation' => 'OriginalPassword123'])->assertRedirect('/clientes');
        Notification::assertNothingSent();
        $this->postJson('/clientes/qr')->assertOk()->assertJsonStructure(['token']);
        $this->assertNull(Customer::firstOrFail()->email_verified_at);
    }

    public function test_schedule_is_free_authoritative_idempotent_and_protected_from_expiry(): void
    {
        extract($this->data());
        $url = "/loyalty/redemptions/$r->id/appointment";
        $this->actingAs($owner)->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 1);
        $a = Appointment::firstOrFail();
        $this->assertSame($c->name, $a->client_name);
        $this->assertSame($c->id, $a->customer_id);
        $this->assertSame('0.00', $a->expected_total);
        $this->assertSame('0.00', $a->items->first()->unit_price);
        $this->assertSame('scheduled', $r->fresh()->status);
        app(RescheduleAppointmentAction::class)->execute($owner, $a, ['date' => '2030-01-08', 'start_time' => '10:00']);
        $this->assertSame('0.00', $a->fresh()->expected_total);
        $this->assertSame('0.00', $a->fresh()->items->first()->line_total);
        $this->assertSame('scheduled', $r->fresh()->status);
        $this->travel(2)->days();
        app(LoyaltyService::class)->expireDue();
        $this->assertSame('scheduled', $r->fresh()->status);
        $this->assertSame(50, app(LoyaltyService::class)->balance($c));
        $this->post("/appointments/$a->id/complete-reward", ['confirmed' => true])->assertSessionHasNoErrors();
        $this->post("/appointments/$a->id/complete-reward", ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $r->fresh()->status);
        $this->assertSame('completed', $a->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('appointment_deposits', 0);
        $this->assertSame(0, (int) $c->entries()->where('type', 'award')->count());
        $this->assertSame(50, app(LoyaltyService::class)->balance($c));
    }

    public function test_free_appointments_cannot_be_charged_or_given_deposits_or_changed_to_paid_services(): void
    {
        extract($this->data());
        $this->actingAs($owner)->post("/loyalty/redemptions/$r->id/appointment", $input)->assertSessionHasNoErrors();
        $a = Appointment::firstOrFail();
        $this->get('/sales/new?appointment='.$a->id)->assertForbidden();
        $payload = ['checkout_token' => (string) Str::uuid(), 'payment_method' => 'cash', 'removed_appointment_item_ids' => [], 'items' => [['appointment_item_id' => $a->items->first()->id, 'service_id' => $s->id, 'quantity' => 1, 'performed_by' => $employee->id]]];
        $this->post("/appointments/$a->id/checkout", $payload)->assertSessionHasErrors('appointment');
        $this->get("/appointments/$a->id/edit")->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        try {
            app(RecordAppointmentDepositAction::class)->execute($owner, $a, ['amount' => '10.00', 'payment_method' => 'cash']);
            $this->fail('Deposit accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('appointment_deposits', 0);
        }
    }

    public function test_cancelled_reward_appointment_returns_points_and_stock_once(): void
    {
        extract($this->data());
        $this->actingAs($owner)->post("/loyalty/redemptions/$r->id/appointment", $input)->assertSessionHasNoErrors();
        $a = Appointment::firstOrFail();
        app(CancelAppointmentAction::class)->execute($owner, $a, 'La clienta solicitó cancelar');
        app(LoyaltyService::class)->release($r);
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame(100, app(LoyaltyService::class)->balance($c));
        $this->assertSame(1, $reward->fresh()->stock);
    }

    public function test_wrong_service_quantity_and_deposit_are_rejected_without_booking(): void
    {
        extract($this->data());
        $this->actingAs($owner);
        foreach ([['quantity' => 2], ['service_id' => 999]] as $change) {
            $data = $input;
            $data['items'][0] = [...$data['items'][0], ...$change];
            $this->post("/loyalty/redemptions/$r->id/appointment", $data)->assertSessionHasErrors();
        }
        $this->post("/loyalty/redemptions/$r->id/appointment", [...$input, 'has_deposit' => true])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_completion_before_start_or_by_unassigned_employee_is_rejected(): void
    {
        extract($this->data());
        $this->actingAs($owner)->post("/loyalty/redemptions/$r->id/appointment", $input)->assertSessionHasNoErrors();
        $a = Appointment::firstOrFail();
        $this->post("/appointments/$a->id/complete-reward", ['confirmed' => true])->assertSessionHasErrors('appointment');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('employee');
        $other->givePermissionTo('loyalty.redeem');
        $this->actingAs($other)->post("/appointments/$a->id/complete-reward", ['confirmed' => true])->assertForbidden();
        $this->assertSame('scheduled', $a->fresh()->status);
    }

    public function test_reward_photo_is_private_reencoded_and_replaceable(): void
    {
        Storage::fake('local');
        extract($this->data());
        $this->actingAs($owner);
        $data = ['name' => $s->name, 'service_id' => $s->id, 'benefit_type' => 'service', 'points_cost' => 50, 'stock' => 1, 'per_customer_limit' => 1, 'is_active' => true, 'version' => 1, 'image' => UploadedFile::fake()->image('premio.jpg', 200, 200)];
        $this->post('/configuration/loyalty/rewards', $data)->assertSessionHasNoErrors();
        $new = LoyaltyReward::latest('id')->firstOrFail();
        $old = $new->image_path;
        $this->assertStringEndsWith('.webp', $old);
        $this->assertStringStartsWith('RIFF', Storage::disk('local')->get($old));
        $this->get('/rewards/'.$new->id.'/image')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->put('/configuration/loyalty/rewards/'.$new->id, [...$data, 'image' => null, 'remove_image' => true])->assertSessionHasNoErrors();
        $this->assertNull($new->fresh()->image_path);
        Storage::disk('local')->assertMissing($old);
    }

    public function test_photo_replacement_cleans_old_file_and_stale_edits_leave_no_orphans(): void
    {
        Storage::fake('local');
        extract($this->data());
        $this->actingAs($owner);
        $data = ['name' => $s->name, 'service_id' => $s->id, 'benefit_type' => 'service', 'points_cost' => 50, 'per_customer_limit' => 1, 'is_active' => true, 'version' => 1, 'image' => UploadedFile::fake()->image('first.png')];
        $this->put('/configuration/loyalty/rewards/'.$reward->id, $data)->assertSessionHasNoErrors();
        $old = $reward->fresh()->image_path;
        $oldUrl = $reward->fresh()->image_url;
        $this->put('/configuration/loyalty/rewards/'.$reward->id, [...$data, 'version' => 2, 'image' => UploadedFile::fake()->image('next.png')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $current = $reward->fresh()->image_path;
        $this->assertNotSame($oldUrl, $reward->fresh()->image_url);
        $this->put('/configuration/loyalty/rewards/'.$reward->id, [...$data, 'image' => UploadedFile::fake()->image('stale.png')])->assertSessionHasErrors('version');
        $this->assertSame([$current], Storage::disk('local')->allFiles('reward-images'));
        $this->put('/configuration/loyalty/rewards/'.$reward->id, [...$data, 'version' => 3, 'image' => UploadedFile::fake()->create('bad.jpg', 10, 'image/jpeg')])->assertSessionHasErrors('image');
        $this->assertSame([$current], Storage::disk('local')->allFiles('reward-images'));
        auth('web')->logout();
        $this->get('/rewards/'.$reward->id.'/image')->assertForbidden();
        $this->actingAs($c, 'customer')->get('/rewards/'.$reward->id.'/image')->assertOk();
    }

    public function test_scheduled_redemption_cannot_be_released_independently_of_active_appointment(): void
    {
        extract($this->data());
        $this->actingAs($owner)->post("/loyalty/redemptions/$r->id/appointment", $input)->assertSessionHasNoErrors();
        try {
            app(LoyaltyService::class)->release($r);
            $this->fail('Active appointment released');
        } catch (ValidationException) {
            $this->assertSame('scheduled', $r->fresh()->status);
            $this->assertSame(50, app(LoyaltyService::class)->balance($c));
        }
    }

    public function test_no_show_returns_points_and_stock_and_cannot_be_completed(): void
    {
        extract($this->data());
        $this->actingAs($owner)->post("/loyalty/redemptions/$r->id/appointment", $input)->assertSessionHasNoErrors();
        $a = Appointment::firstOrFail();
        $this->travel(3)->hours();
        app(MarkAppointmentNoShowAction::class)->execute($owner, $a, 'No llegó a la cita de premio');
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame(100, app(LoyaltyService::class)->balance($c));
        $this->assertSame(1, $reward->fresh()->stock);
        $this->post("/appointments/$a->id/complete-reward", ['confirmed' => true])->assertSessionHasErrors('appointment');
        $this->assertDatabaseCount('sales', 0);
    }
}
