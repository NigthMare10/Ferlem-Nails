<?php

namespace Tests\Feature;

use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\CheckoutAppointmentAction;
use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\MarkAppointmentNoShowAction;
use App\Actions\Appointments\RecordAppointmentDepositAction;
use App\Actions\Appointments\RefundAppointmentDepositExcessAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Actions\Appointments\ResolveAppointmentDepositAction;
use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-20 14:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_permission_is_only_assigned_to_owner_and_administrator(): void
    {
        $this->assertTrue(Role::findByName('owner')->hasPermissionTo(Permissions::NOTIFICATIONS_ACCESS));
        $this->assertTrue(Role::findByName('administrator')->hasPermissionTo(Permissions::NOTIFICATIONS_ACCESS));
        $this->assertFalse(Role::findByName('employee')->hasPermissionTo(Permissions::NOTIFICATIONS_ACCESS));
    }

    public function test_publication_only_targets_active_authorized_owner_and_administrator_without_secrets(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $inactiveOwner = $this->user('owner', false);
        $employee = $this->user('employee');

        $this->publish($employee, 'test.created', 'fact:1');

        $this->assertSame(1, $owner->internalNotifications()->count());
        $this->assertSame(1, $administrator->internalNotifications()->count());
        $this->assertSame(0, $inactiveOwner->internalNotifications()->count());
        $this->assertSame(0, $employee->internalNotifications()->count());
        $payload = $owner->internalNotifications()->firstOrFail()->data;
        $this->assertSame(
            ['type', 'title', 'message', 'url', 'actor', 'entity', 'occurred_at'],
            array_keys($payload),
        );
        $this->assertSame(['id', 'name'], array_keys($payload['actor']));
        $this->assertStringNotContainsString('password', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('token', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_notification_routes_enforce_rbac_and_recipient_ownership(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $this->publish($employee, 'test.created', 'fact:ownership');
        $ownerNotification = $owner->internalNotifications()->firstOrFail();

        $this->get('/notifications')->assertRedirect(route('login'));
        $this->actingAs($employee)->getJson('/notifications')->assertForbidden();
        $this->actingAs($administrator)
            ->patchJson("/notifications/{$ownerNotification->id}/read")
            ->assertNotFound();
        $this->assertNull($ownerNotification->fresh()->read_at);

        $this->actingAs($owner)->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'test.created');
    }

    public function test_individual_and_bulk_read_are_idempotent_and_only_change_owned_rows(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $this->publish($owner, 'test.first', 'fact:first');
        $this->publish($owner, 'test.second', 'fact:second');
        $first = $owner->internalNotifications()->oldest()->firstOrFail();

        $this->actingAs($owner)->patchJson("/notifications/{$first->id}/read")->assertOk();
        $this->patchJson("/notifications/{$first->id}/read")->assertOk();
        $this->assertSame(1, $owner->internalNotifications()->whereNull('read_at')->count());

        $this->patchJson('/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(0, $owner->internalNotifications()->whereNull('read_at')->count());
        $this->assertSame(2, $administrator->internalNotifications()->whereNull('read_at')->count());
    }

    public function test_inertia_shares_counts_and_recent_only_with_authorized_users(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $this->publish($employee, 'test.shared', 'fact:shared');

        $this->actingAs($owner)->get('/configuration/users')->assertInertia(fn (Assert $page) => $page
            ->where('auth.notifications.unread_count', 1)
            ->where('auth.notifications.recent.0.type', 'test.shared'));
        $this->actingAs($employee)->get('/sales/new')->assertInertia(fn (Assert $page) => $page
            ->missing('auth.notifications'));
    }

    public function test_duplicate_fact_is_ignored_and_outer_rollback_removes_publication(): void
    {
        $owner = $this->user('owner');
        $this->publish($owner, 'test.idempotent', 'same-fact');
        $this->publish($owner, 'test.idempotent', 'same-fact');
        $this->assertSame(1, $owner->internalNotifications()->count());

        try {
            DB::transaction(function () use ($owner) {
                $this->publish($owner, 'test.rollback', 'rolled-back-fact');
                throw new RuntimeException('induced rollback');
            });
            $this->fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('induced rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('notifications', ['dedupe_key' => 'rolled-back-fact']);
    }

    public function test_card_sale_retry_publishes_one_sale_fact_per_recipient(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $service = $this->service();
        $token = (string) Str::uuid();
        $payload = [
            'checkout_token' => $token,
            'payment_method' => Sale::PAYMENT_METHOD_CARD,
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ];

        $this->actingAs($employee)->post('/sales', $payload)->assertStatus(303);
        $this->post('/sales', $payload)->assertStatus(303);

        foreach ([$owner, $administrator] as $recipient) {
            $this->assertSame(1, $recipient->internalNotifications()->where('data->type', 'sale.completed')->count());
            $this->assertSame(1, $recipient->internalNotifications()->where('data->type', 'sale.card_payment_recorded')->count());
        }
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_appointment_lifecycle_events_publish_their_distinct_facts(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service();
        $rescheduled = $this->appointment($owner, $employee, $service, '09:00');
        $canceled = $this->appointment($owner, $employee, $service, '10:00');
        $noShow = $this->appointment($owner, $employee, $service, '11:00');
        $completed = $this->appointment($owner, $employee, $service, '12:00');

        app(RescheduleAppointmentAction::class)->execute($owner, $rescheduled, [
            'date' => '2026-07-26', 'start_time' => '09:00', 'assignments' => [], 'reschedule_note' => null,
        ]);
        app(CancelAppointmentAction::class)->execute($owner, $canceled, 'Cancelación de prueba.');
        Carbon::setTestNow('2026-07-25 18:00:00 UTC');
        app(MarkAppointmentNoShowAction::class)->execute($owner, $noShow, 'No asistió.');
        app(CheckoutAppointmentAction::class)->execute($owner, $completed, $this->checkoutPayload($completed));

        foreach ([
            'appointment.created' => 4,
            'appointment.rescheduled' => 1,
            'appointment.canceled' => 1,
            'appointment.no_show' => 1,
            'appointment.completed' => 1,
            'sale.from_appointment' => 1,
            'sale.card_payment_recorded' => 1,
        ] as $type => $count) {
            $this->assertSame($count, $owner->internalNotifications()->where('data->type', $type)->count(), $type);
        }
    }

    public function test_deposit_events_cover_record_full_partial_retained_and_excess_refunds(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $service = $this->service();
        $full = $this->appointment($owner, $employee, $service, '09:00');
        $partial = $this->appointment($owner, $employee, $service, '10:00');
        $retained = $this->appointment($owner, $employee, $service, '11:00');
        $excess = $this->appointment($owner, $employee, $service, '12:00');
        foreach ([$full, $partial, $retained, $excess] as $appointment) {
            app(RecordAppointmentDepositAction::class)->execute($owner, $appointment, [
                'amount' => '60.00', 'payment_method' => 'cash',
            ]);
        }

        app(CancelAppointmentAction::class)->execute($owner, $full, 'Prueba.', $this->resolution(ResolveAppointmentDepositAction::FULL_REFUND));
        app(CancelAppointmentAction::class)->execute($owner, $partial, 'Prueba.', $this->resolution(ResolveAppointmentDepositAction::PARTIAL_REFUND, '20.00'));
        app(CancelAppointmentAction::class)->execute($owner, $retained, 'Prueba.', $this->resolution(ResolveAppointmentDepositAction::FULL_RETENTION));
        app(RefundAppointmentDepositExcessAction::class)->execute($owner, $excess, [
            'amount' => '10.00', 'operation_token' => (string) Str::uuid(), 'note' => null,
        ]);

        foreach ([
            'appointment.deposit_recorded' => 4,
            'appointment.deposit_refunded' => 1,
            'appointment.deposit_partially_refunded' => 1,
            'appointment.deposit_retained' => 1,
            'appointment.deposit_excess_refunded' => 1,
        ] as $type => $count) {
            $this->assertSame($count, $owner->internalNotifications()->where('data->type', $type)->count(), $type);
        }
    }

    public function test_user_and_service_create_and_effective_status_changes_are_transactional_events(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->post('/configuration/users', [
            'name' => 'Nueva', 'email' => 'nueva@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'employee', 'is_active' => true,
        ])->assertSessionHas('success');
        $createdUser = User::query()->where('email', 'nueva@example.com')->firstOrFail();
        $this->patch("/configuration/users/{$createdUser->id}/status", ['is_active' => false])->assertSessionHas('success');
        $this->patch("/configuration/users/{$createdUser->id}/status", ['is_active' => false])->assertSessionHas('success');

        $servicePayload = ['name' => 'Gel', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true];
        $this->post('/configuration/services', $servicePayload)->assertSessionHas('success');
        $service = Service::query()->where('name', 'Gel')->firstOrFail();
        $this->patch("/configuration/services/{$service->id}/status", ['is_active' => false])->assertSessionHas('success');
        $this->patch("/configuration/services/{$service->id}/status", ['is_active' => false])->assertSessionHas('success');

        foreach (['user.created', 'user.deactivated', 'service.created', 'service.deactivated'] as $type) {
            $this->assertSame(1, $owner->internalNotifications()->where('data->type', $type)->count(), $type);
        }
    }

    private function publish(User $actor, string $type, string $dedupeKey): void
    {
        app(PublishInternalNotificationAction::class)->execute(
            $actor,
            $type,
            'Título de prueba',
            'Mensaje de prueba.',
            '/test',
            ['type' => 'test', 'id' => 1],
            $dedupeKey,
            now('UTC'),
        );
    }

    private function user(string $role, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    private function service(): Service
    {
        return Service::query()->create([
            'name' => 'Manicura', 'description' => 'Prueba', 'duration_minutes' => 30,
            'price' => '100.00', 'is_active' => true,
        ]);
    }

    private function appointment(User $owner, User $employee, Service $service, string $time): Appointment
    {
        return app(CreateAppointmentAction::class)->execute($owner, [
            'client_name' => 'María López', 'date' => '2026-07-25', 'start_time' => $time,
            'items' => [[
                'service_id' => $service->id, 'assigned_to' => $employee->id,
                'quantity' => 1, 'duration_minutes' => 30,
            ]],
        ]);
    }

    private function checkoutPayload(Appointment $appointment): array
    {
        return [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'card',
            'items' => $appointment->items->map(fn ($item) => [
                'appointment_item_id' => $item->id,
                'service_id' => $item->service_id,
                'quantity' => $item->quantity,
                'performed_by' => $item->assigned_to,
            ])->all(),
            'removed_appointment_item_ids' => [],
        ];
    }

    private function resolution(string $resolution, ?string $amount = null): array
    {
        return array_filter([
            'deposit_resolution' => $resolution,
            'refund_amount' => $amount,
            'operation_token' => in_array($resolution, [
                ResolveAppointmentDepositAction::FULL_REFUND,
                ResolveAppointmentDepositAction::PARTIAL_REFUND,
            ], true) ? (string) Str::uuid() : null,
            'resolution_notes' => 'Prueba.',
        ], fn ($value) => $value !== null);
    }
}
