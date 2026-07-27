<?php

namespace Tests\Feature;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEvent;
use App\Models\User;
use App\Support\ExpenseAttachmentStorage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class Phase6AExpenseNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(ExpenseAttachmentStorage::DISK);
    }

    public function test_notifications_are_published_after_committed_create_update_and_cancel(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $this->actingAs($owner)->post('/expenses', $this->payload())->assertRedirect();
        $expense = Expense::query()->firstOrFail();
        $this->assertSame(1, $administrator->internalNotifications()->where('data->type', 'expense.created')->count());

        $this->put(route('expenses.update', $expense), [
            'expense_date' => now('America/Tegucigalpa')->format('Y-m-d'),
            'category_id' => $expense->category_id,
            'description' => 'Compra actualizada', 'amount' => '150.00',
            'payment_method' => 'card', 'vendor' => null, 'employee_id' => null,
        ])->assertStatus(303);
        $this->assertSame(1, $administrator->internalNotifications()->where('data->type', 'expense.updated')->count());

        $this->post(route('expenses.cancel', $expense), ['cancellation_reason' => 'Registro incorrecto.'])->assertStatus(303);
        $this->assertSame(1, $administrator->internalNotifications()->where('data->type', 'expense.canceled')->count());
    }

    public function test_rolled_back_expense_does_not_notify_or_persist(): void
    {
        $owner = $this->user('owner');
        $this->withoutExceptionHandling();
        Event::listen('eloquent.creating: '.ExpenseEvent::class, fn () => throw new RuntimeException('forced rollback'));
        try {
            $this->actingAs($owner)->post('/expenses', $this->payload());
            $this->fail('La operación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced rollback', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.ExpenseEvent::class);
        }
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_failure_after_commit_does_not_turn_committed_expense_into_failure(): void
    {
        $owner = $this->user('owner');
        $publisher = Mockery::mock(PublishInternalNotificationAction::class);
        $publisher->shouldReceive('execute')->once()->andThrow(new RuntimeException('notification unavailable'));
        $this->app->instance(PublishInternalNotificationAction::class, $publisher);

        $this->actingAs($owner)->post('/expenses', $this->payload())->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('expense_events', 1);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(): array
    {
        return [
            'checkout_token' => (string) Str::uuid(),
            'expense_date' => now('America/Tegucigalpa')->format('Y-m-d'),
            'category_id' => ExpenseCategory::query()->where('slug', 'materiales-e-implementos')->value('id'),
            'description' => 'Compra de materiales', 'amount' => '125.50',
            'payment_method' => 'cash', 'vendor' => null, 'employee_id' => null,
        ];
    }
}
