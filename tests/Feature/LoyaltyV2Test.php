<?php

namespace Tests\Feature;

use App\Actions\Loyalty\GiftService;
use App\Actions\Loyalty\LoyaltyService;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyGiftBatch;
use App\Models\LoyaltyReward;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoyaltyV2Test extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole('owner');
        $this->client = $this->client();
        $this->service = Service::create(['name' => 'Manicura de prueba', 'duration_minutes' => 60, 'price' => 350, 'loyalty_points' => 35, 'is_active' => true]);
    }

    private function client(): Customer
    {
        $c = Customer::create(['name' => 'Clienta '.Str::random(6), 'email' => Str::uuid().'@example.test', 'password' => 'Prueba!2026']);
        $c->forceFill(['email_verified_at' => now()])->save();
        LoyaltyEntry::create(['customer_id' => $c->id, 'points' => 1000, 'qualifying_points' => 200, 'type' => 'award', 'source_key' => (string) Str::uuid()]);

        return $c;
    }

    private function payload(array $changes = []): array
    {
        return [...['operation_token' => (string) Str::uuid(), 'customer_ids' => [$this->client->id], 'type' => 'points', 'points' => 200, 'reason' => 'Cortesía de prueba', 'message' => 'Gracias por visitarnos'], ...$changes];
    }

    public static function invalidGifts(): array
    {
        $cases = [];
        foreach ([0, -1, 100001, 1.5, 'abc', null] as $i => $value) {
            $cases['points '.$i] = [['points' => $value]];
        }
        foreach (['gift', '', null] as $i => $value) {
            $cases['type '.$i] = [['type' => $value]];
        }
        foreach ([[], 'all', [999999], ['abc']] as $i => $value) {
            $cases['recipients '.$i] = [['customer_ids' => $value]];
        }
        foreach (['bad', null, ''] as $i => $value) {
            $cases['token '.$i] = [['operation_token' => $value]];
        }
        foreach (['x', str_repeat('x', 501), null, ''] as $i => $value) {
            $cases['reason '.$i] = [['reason' => $value]];
        }
        foreach ([str_repeat('x', 501), ['array']] as $i => $value) {
            $cases['message '.$i] = [['message' => $value]];
        }
        foreach ([null, 999999, 'abc'] as $i => $value) {
            $cases['service '.$i] = [['type' => 'service', 'service_id' => $value]];
        }
        foreach (['yesterday', '2020-01-01T12:00', '2026-99-99T99:99'] as $i => $value) {
            $cases['expiry '.$i] = [['type' => 'service', 'service_id' => 'existing', 'expires_at' => $value]];
        }

        return $cases;
    }

    #[DataProvider('invalidGifts')]
    public function test_invalid_gift_does_not_create_partial_ledger(array $changes): void
    {
        if (($changes['service_id'] ?? '') === 'existing') {
            $changes['service_id'] = $this->service->id;
        }
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/gifts', $this->payload($changes))->assertUnprocessable();
        $this->assertDatabaseCount('loyalty_gift_batches', 0);
        $this->assertSame(1000, app(LoyaltyService::class)->balance($this->client));
    }

    public static function grantMatrix(): array
    {
        $cases = [];
        foreach ([1, 2, 3, 20] as $count) {
            foreach ([1, 200, 100000] as $points) {
                $cases["$count clients $points pts"] = ['points', $count, $points];
            }
            $cases["$count clients service"] = ['service', $count, 0];
        }

        return $cases;
    }

    #[DataProvider('grantMatrix')]
    public function test_gift_matrix_is_idempotent_and_never_changes_level_or_sales(string $type, int $count, int $points): void
    {
        $clients = collect([$this->client]);
        for ($i = 1; $i < $count; $i++) {
            $clients->push($this->client());
        }
        $payload = $this->payload(['type' => $type, 'points' => $points, 'service_id' => $this->service->id, 'customer_ids' => $clients->pluck('id')->all()]);
        $this->actingAs($this->owner)->post('/configuration/loyalty/gifts', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/configuration/loyalty/gifts', $payload)->assertSessionHasNoErrors();
        $batch = LoyaltyGiftBatch::sole();
        $this->post('/configuration/loyalty/gifts/'.$batch->id.'/retry')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loyalty_gift_batches', 1);
        $this->assertDatabaseCount('loyalty_gift_recipients', $count);
        foreach ($clients as $client) {
            $this->assertSame(1000 + ($type === 'points' ? $points : 0), app(LoyaltyService::class)->balance($client));
            $this->assertSame(200, (int) $client->entries()->sum('qualifying_points'));
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame($type === 'points' ? $count : 0, LoyaltyEntry::where('type', 'gift')->count());
    }

    public static function roles(): array
    {
        $cases = [];
        foreach (['owner', 'administrator', 'employee'] as $role) {
            foreach (['create', 'retry', 'customers', 'detail', 'resolve', 'levels', 'bulk', 'index'] as $endpoint) {
                $cases["$role $endpoint"] = [$role, $endpoint];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_role_permissions_on_every_admin_flow(string $role, string $endpoint): void
    {
        $batch = app(GiftService::class)->send($this->owner, $this->payload(['type' => 'service', 'service_id' => $this->service->id]));
        $recipient = $batch->recipients()->first();
        $reward = $this->reward();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->assignRole($role);
        $this->actingAs($actor);
        $response = match ($endpoint) {
            'create' => $this->postJson('/configuration/loyalty/gifts', $this->payload()),
            'retry' => $this->postJson("/configuration/loyalty/gifts/$batch->id/retry"),
            'customers' => $this->getJson('/configuration/loyalty/gift-customers'),
            'detail' => $this->getJson("/configuration/loyalty/gifts/$batch->id"),
            'resolve' => $this->postJson("/configuration/loyalty/gift-recipients/$recipient->id", ['action' => 'confirmed', 'verified' => true]),
            'levels' => $this->putJson('/configuration/loyalty/settings', ['levels' => [['name' => 'Base', 'points' => 0, 'benefits' => 'Programa base']]]),
            'bulk' => $this->postJson('/configuration/loyalty/rewards/bulk-status', ['items' => [['id' => $reward->id, 'version' => 1]], 'is_active' => false]),
            'index' => $this->get('/configuration/loyalty/rewards'),
        };
        $allowed = $role === 'owner' || $role === 'administrator' && in_array($endpoint, ['detail', 'resolve', 'bulk', 'index']);
        if ($allowed) {
            $this->assertTrue($response->status() < 400, $response->getContent());
        } else {
            $response->assertForbidden();
        }
    }

    public static function lifecycle(): array
    {
        $cases = [];
        foreach (['available', 'confirmed', 'cancelled', 'expired'] as $status) {
            foreach (['confirmed', 'cancelled', 'expire'] as $action) {
                $cases["$status to $action"] = [$status, $action];
            }
        }

        return $cases;
    }

    #[DataProvider('lifecycle')]
    public function test_service_gift_state_transitions_preserve_ledger(string $status, string $action): void
    {
        $batch = app(GiftService::class)->send($this->owner, $this->payload(['type' => 'service', 'service_id' => $this->service->id, 'expires_at' => now()->addDay()->format('Y-m-d H:i:s')]));
        $recipient = $batch->recipients()->first();
        $recipient->update(['status' => $status]);
        $this->actingAs($this->owner);
        if ($action === 'expire') {
            $this->travel(25)->hours();
            $this->artisan('loyalty:expire')->assertSuccessful();
            $expected = $status === 'available' ? 'expired' : $status;
        } else {
            $response = $this->postJson("/configuration/loyalty/gift-recipients/$recipient->id", ['action' => $action, 'verified' => true, 'note' => 'Prueba de cancelación']);
            $allowed = $status === 'available' || $status === $action;
            $allowed ? $response->assertRedirect() : $response->assertUnprocessable();
            $expected = $allowed ? $action : $status;
        }
        $this->assertSame($expected, $recipient->fresh()->status);
        $this->assertSame(1000, app(LoyaltyService::class)->balance($this->client));
        $this->assertSame(200, (int) $this->client->entries()->sum('qualifying_points'));
    }

    private function reward(array $changes = []): LoyaltyReward
    {
        return LoyaltyReward::create([...['name' => $this->service->name, 'service_id' => $this->service->id, 'benefit_type' => 'service', 'points_cost' => 50, 'stock' => 5, 'per_customer_limit' => 10, 'is_active' => true], ...$changes]);
    }

    public static function levelBoundaries(): array
    {
        return [['Brillo', 0], ['Brillo', 99], ['Glow', 100], ['Glow', 999], ['Amatista', 1000], ['Amatista', 4999], ['Diamante', 5000], ['Diamante', 10000000]];
    }

    #[DataProvider('levelBoundaries')]
    public function test_exact_level_boundaries_and_maximum_are_present_in_portal(string $level, int $points): void
    {
        $levels = [['name' => 'Brillo', 'points' => 0, 'benefits' => 'Base'], ['name' => 'Glow', 'points' => 100, 'benefits' => 'Glow'], ['name' => 'Amatista', 'points' => 1000, 'benefits' => 'Amatista'], ['name' => 'Diamante', 'points' => 5000, 'benefits' => 'Máximo']];
        $this->actingAs($this->owner)->put('/configuration/loyalty/settings', ['levels' => $levels, 'version' => 1])->assertSessionHasNoErrors();
        LoyaltyEntry::create(['customer_id' => $this->client->id, 'points' => 0, 'qualifying_points' => $points - 200, 'type' => 'award', 'source_key' => (string) Str::uuid()]);
        $this->actingAs($this->client, 'customer')->get('/clientes')->assertInertia(fn ($p) => $p->where('qualifying', $points)->where('levels.3.name', 'Diamante'));
        $resolved = collect($levels)->last(fn ($l) => $l['points'] <= $points);
        $this->assertSame($level, $resolved['name']);
    }

    public static function invalidLevels(): array
    {
        return [
            'empty' => [[]], 'not zero' => [[['name' => 'A', 'points' => 1, 'benefits' => 'A']]],
            'negative' => [[['name' => 'A', 'points' => -1, 'benefits' => 'A']]],
            'duplicate points' => [[['name' => 'A', 'points' => 0, 'benefits' => 'A'], ['name' => 'B', 'points' => 0, 'benefits' => 'B']]],
            'duplicate names' => [[['name' => 'A', 'points' => 0, 'benefits' => 'A'], ['name' => 'a', 'points' => 10, 'benefits' => 'B']]],
            'no name' => [[['name' => '', 'points' => 0, 'benefits' => 'A']]],
            'long name' => [[['name' => str_repeat('A', 51), 'points' => 0, 'benefits' => 'A']]],
            'no benefits' => [[['name' => 'A', 'points' => 0, 'benefits' => '']]],
            'huge' => [[['name' => 'A', 'points' => 0, 'benefits' => 'A'], ['name' => 'B', 'points' => 10000001, 'benefits' => 'B']]],
            'fraction' => [[['name' => 'A', 'points' => 0, 'benefits' => 'A'], ['name' => 'B', 'points' => 1.5, 'benefits' => 'B']]],
        ];
    }

    #[DataProvider('invalidLevels')]
    public function test_invalid_levels_preserve_configuration(array $levels): void
    {
        $before = DB::table('loyalty_settings')->value('levels');
        $this->actingAs($this->owner)->putJson('/configuration/loyalty/settings', ['levels' => $levels])->assertUnprocessable();
        $this->assertSame($before, DB::table('loyalty_settings')->value('levels'));
    }

    public function test_partial_batch_retry_after_account_reactivation_never_duplicates_points(): void
    {
        $other = $this->client();
        $other->forceFill(['is_active' => false])->save();
        $batch = app(GiftService::class)->send($this->owner, $this->payload(['customer_ids' => [$other->id, $this->client->id]]));
        $this->assertSame(1, $batch->recipients()->where('status', 'failed')->count());
        $this->assertSame(1200, app(LoyaltyService::class)->balance($this->client));
        $other->forceFill(['is_active' => true])->save();
        app(GiftService::class)->process($this->owner, $batch);
        $this->assertSame(2, LoyaltyEntry::where('type', 'gift')->count());
        $this->assertSame(1200, app(LoyaltyService::class)->balance($other));
    }

    public function test_changed_token_payload_or_actor_cannot_reuse_operation(): void
    {
        $data = $this->payload();
        $this->actingAs($this->owner)->post('/configuration/loyalty/gifts', $data)->assertSessionHasNoErrors();
        $this->postJson('/configuration/loyalty/gifts', [...$data, 'points' => 300])->assertUnprocessable();
        $this->assertSame(1200, app(LoyaltyService::class)->balance($this->client));
    }

    public function test_customer_portal_is_private_and_preserves_service_snapshot(): void
    {
        $batch = app(GiftService::class)->send($this->owner, $this->payload(['type' => 'service', 'service_id' => $this->service->id]));
        $other = $this->client();
        $this->service->update(['name' => 'Nombre nuevo', 'is_active' => false]);
        $this->actingAs($other, 'customer')->get('/clientes/recompensas')->assertInertia(fn ($p) => $p->has('gifts.data', 0));
        $this->actingAs($this->client, 'customer')->withSession(['customer_password_signature' => hash('sha256', $this->client->getAuthPassword())])->get('/clientes/recompensas')->assertInertia(fn ($p) => $p->where('gifts.data.0.batch.service_snapshot.name', 'Manicura de prueba'));
        $this->actingAs($other, 'customer')->getJson('/configuration/loyalty/gifts/'.$batch->id)->assertUnauthorized();
    }

    public function test_inactive_service_cannot_be_new_gift_or_reserved_reward(): void
    {
        $r = $this->reward();
        $this->service->update(['is_active' => false]);
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/gifts', $this->payload(['type' => 'service', 'service_id' => $this->service->id]))->assertUnprocessable();
        $this->actingAs($this->client, 'customer')->postJson('/clientes/recompensas/'.$r->id.'/canjes', ['operation_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(5, $r->fresh()->stock);
    }

    public function test_bulk_change_is_atomic_and_detects_stale_versions(): void
    {
        $a = $this->reward();
        $b = $this->reward();
        $items = [['id' => $a->id, 'version' => 1], ['id' => $b->id, 'version' => 99]];
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/rewards/bulk-status', ['items' => $items, 'is_active' => false])->assertUnprocessable();
        $this->assertTrue($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
        $items[1]['version'] = 1;
        $this->post('/configuration/loyalty/rewards/bulk-status', ['items' => $items, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($a->fresh()->is_active);
        $this->assertSame(2, $a->fresh()->version);
    }

    public function test_unlimited_reservation_cannot_invent_stock_after_reward_edit(): void
    {
        $r = $this->reward(['stock' => null]);
        $reservation = app(LoyaltyService::class)->reserve($this->client, $r, (string) Str::uuid());
        $r->update(['stock' => 3, 'points_cost' => 999]);
        app(LoyaltyService::class)->release($reservation);
        $this->assertSame(3, $r->fresh()->stock);
        $this->assertSame(1000, app(LoyaltyService::class)->balance($this->client));
    }

    public function test_level_stale_update_preserves_other_administrator_changes(): void
    {
        $levels = [['name' => 'Base', 'points' => 0, 'benefits' => 'Base']];
        $this->actingAs($this->owner)->put('/configuration/loyalty/settings', ['levels' => $levels, 'version' => 1])->assertSessionHasNoErrors();
        $this->putJson('/configuration/loyalty/settings', ['levels' => $levels, 'version' => 1])->assertUnprocessable();
        $this->assertSame(2, DB::table('loyalty_settings')->value('version'));
    }

    public function test_maximum_level_does_not_drop_when_redeeming_multiple_rewards(): void
    {
        LoyaltyEntry::create(['customer_id' => $this->client->id, 'points' => 0, 'qualifying_points' => 10000, 'type' => 'award', 'source_key' => (string) Str::uuid()]);
        for ($i = 0; $i < 3; $i++) {
            app(LoyaltyService::class)->reserve($this->client, $this->reward(), (string) Str::uuid());
        }
        $this->assertSame(10200, (int) $this->client->entries()->sum('qualifying_points'));
        $this->assertSame(850, app(LoyaltyService::class)->balance($this->client));
    }

    public function test_all_filter_selection_excludes_disabled_but_includes_active_unverified_accounts(): void
    {
        $inactive = $this->client();
        $inactive->forceFill(['is_active' => false])->save();
        $unverified = $this->client();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $response = $this->actingAs($this->owner)->getJson('/configuration/loyalty/gift-customers?all=1')->assertOk()->assertJsonCount(2, 'ids');
        $this->assertContains($this->client->id, $response->json('ids'));
        $this->assertContains($unverified->id, $response->json('ids'));
        $this->assertNotContains($inactive->id, $response->json('ids'));
    }

    public function test_duplicate_recipients_are_rejected_instead_of_sending_twice(): void
    {
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/gifts', $this->payload(['customer_ids' => [$this->client->id, $this->client->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('loyalty_gift_batches', 0);
    }

    public function test_batch_size_limit_rejects_501_recipients(): void
    {
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/gifts', $this->payload(['customer_ids' => range(1, 501)]))->assertUnprocessable();
        $this->assertDatabaseCount('loyalty_gift_batches', 0);
    }

    public function test_service_expiry_uses_honduras_and_rejects_delivery_at_exact_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 18:00:00', 'UTC'));
        $this->actingAs($this->owner)->post('/configuration/loyalty/gifts', $this->payload(['type' => 'service', 'service_id' => $this->service->id, 'expires_at' => '2030-01-01T12:01']))->assertSessionHasNoErrors();
        $batch = LoyaltyGiftBatch::sole();
        $this->assertSame('2030-01-01 18:01:00', $batch->expires_at->format('Y-m-d H:i:s'));
        $recipient = $batch->recipients()->first();
        $this->travelTo($batch->expires_at);
        $this->postJson('/configuration/loyalty/gift-recipients/'.$recipient->id, ['action' => 'confirmed', 'verified' => true])->assertUnprocessable();
        $this->assertSame('expired', $recipient->fresh()->status);
        $this->assertSame(1000, app(LoyaltyService::class)->balance($this->client));
    }

    public function test_other_owner_cannot_replay_a_private_operation_token(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->owner)->post('/configuration/loyalty/gifts', $payload)->assertSessionHasNoErrors();
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('owner');
        $this->actingAs($other)->postJson('/configuration/loyalty/gifts', $payload)->assertUnprocessable();
        $this->assertSame(1, LoyaltyEntry::where('type', 'gift')->count());
    }

    public function test_inactive_operator_cannot_create_gifts(): void
    {
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner)->postJson('/configuration/loyalty/gifts', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('loyalty_gift_batches', 0);
    }

    public function test_live_service_name_is_inherited_but_past_reservation_keeps_snapshot(): void
    {
        $reward = $this->reward();
        $first = app(LoyaltyService::class)->reserve($this->client, $reward, (string) Str::uuid());
        $this->service->update(['name' => 'Manicura renovada']);
        $second = app(LoyaltyService::class)->reserve($this->client, $reward, (string) Str::uuid());
        $this->assertSame('Manicura de prueba', $first->fresh()->reward_snapshot['name']);
        $this->assertSame('Manicura renovada', $second->reward_snapshot['name']);
        $this->actingAs($this->client, 'customer')->get('/clientes/recompensas')->assertInertia(fn ($p) => $p->where('rewards.0.name', 'Manicura renovada'));
    }

    public function test_cancellation_reason_is_saved_once_and_does_not_change_on_replay(): void
    {
        $reservation = app(LoyaltyService::class)->reserve($this->client, $this->reward(), (string) Str::uuid());
        $this->actingAs($this->owner)->post('/loyalty/redemptions/'.$reservation->id.'/cancel', ['note' => 'La clienta cambió de opinión'])->assertSessionHasNoErrors();
        $this->post('/loyalty/redemptions/'.$reservation->id.'/cancel', ['note' => 'Otra nota que no debe reemplazarla'])->assertSessionHasNoErrors();
        $this->assertSame('La clienta cambió de opinión', $reservation->fresh()->resolution_note);
        $this->assertNotNull($reservation->fresh()->resolved_at);
        $this->assertSame(1, LoyaltyEntry::where('type', 'release')->count());
    }

    public function test_linked_service_deletion_returns_validation_instead_of_database_failure(): void
    {
        $this->reward();
        $this->actingAs($this->owner)->deleteJson('/configuration/services/'.$this->service->id)->assertUnprocessable();
        $this->assertModelExists($this->service);
    }

    public function test_available_gift_is_never_hidden_behind_paginated_credited_gifts(): void
    {
        $available = app(GiftService::class)->send($this->owner, $this->payload(['type' => 'service', 'service_id' => $this->service->id]));
        for ($i = 0; $i < 25; $i++) {
            app(GiftService::class)->send($this->owner, $this->payload(['points' => 1]));
        }
        $this->actingAs($this->client, 'customer')->get('/clientes/recompensas?obsequios=2')->assertInertia(fn ($p) => $p->has('gifts.data', 6)->where('gifts.data.0.batch.id', $available->id));
    }

    public function test_reward_version_prevents_lost_updates_without_overwriting_price(): void
    {
        $reward = $this->reward();
        $payload = [...$reward->attributesToArray(), 'points_cost' => 100, 'version' => 1];
        $this->actingAs($this->owner)->put('/configuration/loyalty/rewards/'.$reward->id, $payload)->assertSessionHasNoErrors();
        $this->putJson('/configuration/loyalty/rewards/'.$reward->id, [...$payload, 'points_cost' => 999])->assertUnprocessable();
        $this->assertSame(100, $reward->fresh()->points_cost);
        $this->assertSame('350.00', $this->service->fresh()->price);
    }
}
