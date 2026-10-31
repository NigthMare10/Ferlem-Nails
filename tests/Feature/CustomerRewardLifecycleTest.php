<?php

namespace Tests\Feature;

use App\Actions\Loyalty\LoyaltyService;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerRewardLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2030-01-07 12:00:00', 'America/Tegucigalpa'));
    }

    private function customer(array $attributes = []): Customer
    {
        $customer = Customer::create([...['name' => 'Clienta de prueba', 'email' => Str::uuid().'@example.test', 'password' => 'Testing12345', 'birthday' => '1994-01-14'], ...$attributes]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        LoyaltyEntry::create(['customer_id' => $customer->id, 'type' => 'adjustment', 'points' => 200, 'qualifying_points' => 200, 'source_key' => (string) Str::uuid()]);

        return $customer;
    }

    private function reward(array $attributes = []): LoyaltyReward
    {
        return LoyaltyReward::create([...['name' => 'Diseño especial', 'description' => 'Beneficio de prueba', 'points_cost' => 60, 'stock' => 2, 'per_customer_limit' => 3, 'is_active' => true, 'benefit_type' => 'gift'], ...$attributes]);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('owner');

        return $user;
    }

    private function reserve(Customer $customer, LoyaltyReward $reward): LoyaltyRedemption
    {
        return app(LoyaltyService::class)->reserve($customer, $reward, (string) Str::uuid());
    }

    public function test_customer_reservation_debits_once_and_appears_in_both_portals_without_lowering_level(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $token = (string) Str::uuid();
        $this->actingAs($customer, 'customer')->from('/clientes/recompensas')->post("/clientes/recompensas/$reward->id/canjes", ['operation_token' => $token])->assertSessionHasNoErrors()->assertRedirect('/clientes/recompensas');
        $reservation = LoyaltyRedemption::sole();
        $this->assertSame('reserved', $reservation->status);
        $this->assertTrue($reservation->expires_at->equalTo(now()->addHours(24)));
        $this->assertSame(1, $reward->fresh()->stock);
        $this->get('/clientes/recompensas')->assertInertia(fn (Assert $page) => $page->where('balance', 140)->where('qualifying', 200)->where('redemptions.0.id', $reservation->id)->where('rewards.0.used_by_customer', 1));
        $this->post("/clientes/recompensas/$reward->id/canjes", ['operation_token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loyalty_redemptions', 1);
        $this->assertSame(140, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(1, $reward->fresh()->stock);
        $this->actingAs($this->owner(), 'web')->get('/configuration/loyalty/rewards?status=reserved')->assertInertia(fn (Assert $page) => $page->where('redemptions.data.0.id', $reservation->id));
    }

    public function test_confirmed_delivery_is_final_and_neither_recharges_nor_refunds_points(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $owner = $this->owner();
        $this->actingAs($owner)->post("/loyalty/redemptions/$reservation->id/confirm")->assertSessionHasNoErrors();
        $this->post("/loyalty/redemptions/$reservation->id/confirm")->assertSessionHasNoErrors();
        $this->post("/loyalty/redemptions/$reservation->id/cancel");
        $this->travel(25)->hours();
        $this->artisan('loyalty:expire')->assertSuccessful();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame($owner->id, $reservation->fresh()->confirmed_by);
        $this->assertSame(140, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(1, $reward->fresh()->stock);
        $this->assertSame(0, LoyaltyEntry::where('type', 'release')->count());
        $this->actingAs($customer, 'customer')->get('/clientes/recompensas')->assertInertia(fn (Assert $p) => $p->where('redemptions.0.status', 'confirmed')->where('redemption_history.total', 1));
    }

    public function test_cancel_returns_original_cost_and_preserves_snapshot_after_reward_edit(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $reward->update(['name' => 'Nombre cambiado', 'points_cost' => 180]);
        $this->actingAs($this->owner())->post("/loyalty/redemptions/$reservation->id/cancel")->assertSessionHasNoErrors();
        $this->post("/loyalty/redemptions/$reservation->id/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(2, $reward->fresh()->stock);
        $this->assertSame('Diseño especial', $reservation->fresh()->reward_snapshot['name']);
        $this->assertSame(1, LoyaltyEntry::where('type', 'release')->count());
        $this->actingAs($customer, 'customer')->get('/clientes/recompensas')->assertInertia(fn (Assert $p) => $p->where('rewards.0.used_by_customer', 0)->where('redemptions.0.status', 'cancelled'));
    }

    public function test_exact_expiry_refunds_once_and_moves_to_history_without_scheduler(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $this->travelTo($reservation->expires_at);
        $this->actingAs($customer, 'customer')->get('/clientes/recompensas')->assertInertia(fn (Assert $p) => $p->where('balance', 200)->where('qualifying', 200)->where('redemptions.0.status', 'expired')->where('redemption_history.total', 1)->where('rewards.0.used_by_customer', 0));
        $this->get('/clientes/recompensas')->assertSuccessful();
        $this->artisan('loyalty:expire')->assertSuccessful();
        $this->assertSame(1, LoyaltyEntry::where('type', 'release')->count());
        $this->assertSame(2, $reward->fresh()->stock);
    }

    public function test_confirm_after_deadline_fails_but_commits_expiry_and_refund(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $this->travelTo($reservation->expires_at);
        $this->actingAs($this->owner())->postJson("/loyalty/redemptions/$reservation->id/confirm")->assertUnprocessable()->assertJsonValidationErrors('redemption');
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(2, $reward->fresh()->stock);
    }

    public function test_cancelling_an_already_due_reservation_records_expired_not_cancelled(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $this->travelTo($reservation->expires_at);
        $this->actingAs($this->owner())->post("/loyalty/redemptions/$reservation->id/cancel")->assertSessionHasNoErrors();
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
    }

    public function test_replaying_an_expired_operation_does_not_create_another_reservation_or_claim_it_is_new(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $this->travel(25)->hours();
        $this->actingAs($customer, 'customer')->post("/clientes/recompensas/$reward->id/canjes", ['operation_token' => $reservation->operation_token])->assertSessionHas('success', 'Esta operación ya fue procesada. Consulta el estado en tus canjes anteriores.');
        $this->assertDatabaseCount('loyalty_redemptions', 1);
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
    }

    public static function unavailableRewards(): array
    {
        return [
            'inactive' => [['is_active' => false]],
            'no stock' => [['stock' => 0]],
            'not started' => [['starts_on' => '2030-01-08']],
            'ended' => [['ends_on' => '2030-01-06']],
            'birthday in wrong month' => [['birthday_only' => true], ['birthday' => '1994-02-14']],
            'birthday not registered' => [['birthday_only' => true], ['birthday' => null]],
            'not enough points' => [['points_cost' => 201]],
        ];
    }

    #[DataProvider('unavailableRewards')]
    public function test_ineligible_requests_never_touch_stock_or_points(array $rewardData, array $customerData = []): void
    {
        $customer = $this->customer($customerData);
        $reward = $this->reward($rewardData);
        $this->actingAs($customer, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('loyalty_redemptions', 0);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
        $this->assertSame($reward->stock, $reward->fresh()->stock);
    }

    public function test_birthday_and_date_boundary_and_unlimited_stock_are_supported(): void
    {
        $customer = $this->customer();
        $reward = $this->reward(['birthday_only' => true, 'starts_on' => '2030-01-07', 'ends_on' => '2030-01-07', 'stock' => null]);
        $reservation = $this->reserve($customer, $reward);
        $this->assertSame('reserved', $reservation->status);
        app(LoyaltyService::class)->release($reservation);
        $this->assertNull($reward->fresh()->stock);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
    }

    public function test_limit_and_last_stock_are_enforced_for_distinct_requests(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $reward = $this->reward(['stock' => 1, 'per_customer_limit' => 1]);
        $this->reserve($customer, $reward);
        foreach ([$customer, $other] as $actor) {
            $this->actingAs($actor, 'customer')->withSession(['customer_password_signature' => hash('sha256', $actor->getAuthPassword())])->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => (string) Str::uuid()])->assertUnprocessable();
        }
        $this->assertSame(0, $reward->fresh()->stock);
        $this->assertDatabaseCount('loyalty_redemptions', 1);
        $this->assertSame(200, app(LoyaltyService::class)->balance($other));
    }

    public function test_token_cannot_be_reused_by_another_customer_or_for_another_reward(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $reward = $this->reward();
        $another = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        $this->actingAs($other, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => $reservation->operation_token])->assertForbidden();
        $this->actingAs($customer, 'customer')->withSession(['customer_password_signature' => hash('sha256', $customer->getAuthPassword())])->postJson("/clientes/recompensas/$another->id/canjes", ['operation_token' => $reservation->operation_token])->assertForbidden();
        $this->assertDatabaseCount('loyalty_redemptions', 1);
    }

    public function test_active_reservation_is_not_lost_behind_long_paginated_history_and_other_customers_are_excluded(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $reward = $this->reward(['stock' => null, 'per_customer_limit' => 100]);
        $active = $this->reserve($customer, $reward);
        $this->reserve($other, $reward);
        for ($i = 0; $i < 45; $i++) {
            LoyaltyRedemption::create(['customer_id' => $customer->id, 'loyalty_reward_id' => $reward->id, 'operation_token' => (string) Str::uuid(), 'points_cost' => 0, 'status' => 'confirmed', 'expires_at' => now()->subDays(2), 'reward_snapshot' => ['name' => 'Histórico '.$i]]);
        }
        $this->actingAs($customer, 'customer')->get('/clientes/recompensas')->assertInertia(fn (Assert $p) => $p->has('redemptions', 21)->where('redemptions.0.id', $active->id)->where('redemption_history.total', 45)->where('redemption_history.last_page', 3));
        $this->get('/clientes/recompensas?canjes=3')->assertInertia(fn (Assert $p) => $p->has('redemptions', 6)->where('redemptions.0.id', $active->id)->where('redemption_history.current_page', 3));
    }

    public function test_qr_regeneration_invalidates_previous_token_and_expiry_boundary_is_strict(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $first = $this->postJson('/clientes/qr')->assertOk()->json('token');
        $second = $this->postJson('/clientes/qr')->assertOk()->json('token');
        $this->assertDatabaseMissing('customer_qr_tokens', ['token_hash' => hash('sha256', $first)]);
        $this->assertDatabaseHas('customer_qr_tokens', ['token_hash' => hash('sha256', $second)]);
        $this->assertDatabaseMissing('customer_qr_tokens', ['token_hash' => $second]);
        $this->travel(5)->minutes();
        $this->actingAs($this->owner(), 'web')->postJson('/loyalty/qr/resolve', ['token' => $second])->assertUnprocessable();
    }

    public function test_partial_refresh_does_not_fetch_unused_catalogs_or_movement_history(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer')->get('/clientes')->assertSuccessful();
        DB::enableQueryLog();
        $response = $this->get('/clientes/recompensas', ['X-Inertia' => 'true', 'X-Inertia-Version' => \Inertia\Inertia::getVersion(), 'X-Inertia-Partial-Component' => 'Customer/Portal', 'X-Inertia-Partial-Data' => 'balance,qualifying']);
        $response->assertOk()->assertJsonPath('props.balance', 200)->assertJsonPath('props.qualifying', 200)->assertJsonMissingPath('props.entries')->assertJsonMissingPath('props.services')->assertJsonMissingPath('props.appointments');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringNotContainsString('"services"', $queries);
        $this->assertStringNotContainsString('"appointments"', $queries);
    }

    public function test_client_cannot_override_customer_cost_or_stock_in_request(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $reward = $this->reward();
        $this->actingAs($customer, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => (string) Str::uuid(), 'customer_id' => $other->id, 'points_cost' => 1, 'stock' => 999])->assertRedirect();
        $this->assertDatabaseHas('loyalty_redemptions', ['customer_id' => $customer->id, 'points_cost' => 60]);
        $this->assertSame(140, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(200, app(LoyaltyService::class)->balance($other));
        $this->assertSame(1, $reward->fresh()->stock);
    }

    public function test_confirmed_canjes_consume_customer_limit_even_when_stock_remains(): void
    {
        $customer = $this->customer();
        $reward = $this->reward(['per_customer_limit' => 1, 'stock' => 10]);
        $reservation = $this->reserve($customer, $reward);
        $this->actingAs($this->owner(), 'web')->post("/loyalty/redemptions/$reservation->id/confirm")->assertSessionHasNoErrors();
        $this->actingAs($customer, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(9, $reward->fresh()->stock);
        $this->assertDatabaseCount('loyalty_redemptions', 1);
    }

    public function test_cancelled_reservation_frees_limit_for_a_new_operation(): void
    {
        $customer = $this->customer();
        $reward = $this->reward(['per_customer_limit' => 1]);
        $first = $this->reserve($customer, $reward);
        app(LoyaltyService::class)->release($first);
        $second = $this->reserve($customer, $reward);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('reserved', $second->status);
        $this->assertSame(140, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(1, $reward->fresh()->stock);
    }

    public function test_negative_balance_does_not_allow_a_reservation(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        LoyaltyEntry::create(['customer_id' => $customer->id, 'type' => 'reversal', 'points' => -300, 'source_key' => (string) Str::uuid()]);
        $this->actingAs($customer, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(-100, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(2, $reward->fresh()->stock);
    }

    public function test_expiry_command_processes_more_than_one_chunk_and_is_idempotent(): void
    {
        $customer = $this->customer();
        $reward = $this->reward(['points_cost' => 1, 'stock' => 105, 'per_customer_limit' => 200]);
        for ($i = 0; $i < 105; $i++) {
            $this->reserve($customer, $reward);
        }
        $this->assertSame(95, app(LoyaltyService::class)->balance($customer));
        $this->travel(25)->hours();
        $this->artisan('loyalty:expire')->assertSuccessful();
        $this->artisan('loyalty:expire')->assertSuccessful();
        $this->assertSame(105, LoyaltyRedemption::where('status', 'expired')->count());
        $this->assertSame(105, LoyaltyEntry::where('type', 'release')->count());
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(105, $reward->fresh()->stock);
    }

    public function test_date_restrictions_use_honduras_even_after_midnight_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-07 23:30:00', 'America/Tegucigalpa'));
        $reservation = $this->reserve($this->customer(), $this->reward(['starts_on' => '2030-01-07', 'ends_on' => '2030-01-07']));
        $this->assertSame('reserved', $reservation->status);
    }

    public function test_reward_can_have_an_end_date_without_an_optional_start_date(): void
    {
        $this->actingAs($this->owner())->post('/configuration/loyalty/rewards', ['name' => 'Beneficio temporal', 'description' => '', 'points_cost' => 60, 'benefit_type' => 'gift', 'stock' => null, 'per_customer_limit' => 1, 'is_active' => true, 'birthday_only' => false, 'starts_on' => null, 'ends_on' => '2030-01-31'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loyalty_rewards', ['name' => 'Beneficio temporal', 'ends_on' => '2030-01-31']);
    }

    public function test_reversed_points_block_delivery_until_the_reservation_is_released(): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $reservation = $this->reserve($customer, $reward);
        LoyaltyEntry::create(['customer_id' => $customer->id, 'type' => 'reversal', 'points' => -200, 'qualifying_points' => -200, 'source_key' => (string) Str::uuid()]);
        $this->actingAs($this->owner())->postJson("/loyalty/redemptions/$reservation->id/confirm")->assertUnprocessable();
        $this->assertSame('reserved', $reservation->fresh()->status);
        $this->post("/loyalty/redemptions/$reservation->id/cancel")->assertSessionHasNoErrors();
        $this->assertSame(0, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(2, $reward->fresh()->stock);
        $this->assertSame(0, (int) $customer->entries()->sum('qualifying_points'));
    }

    public static function invalidTokens(): array
    {
        return ['missing' => [null], 'empty' => [''], 'malformed' => ['not-a-uuid']];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_operation_tokens_never_change_the_ledger(?string $token): void
    {
        $customer = $this->customer();
        $reward = $this->reward();
        $this->actingAs($customer, 'customer')->postJson("/clientes/recompensas/$reward->id/canjes", ['operation_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('operation_token');
        $this->assertDatabaseCount('loyalty_redemptions', 0);
        $this->assertSame(200, app(LoyaltyService::class)->balance($customer));
        $this->assertSame(2, $reward->fresh()->stock);
    }
}
