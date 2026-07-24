<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4FAppointmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-24 14:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_history_route_is_named_and_resolves_before_dynamic_detail(): void
    {
        $this->assertTrue(Route::has('appointments.history'));
        $route = Route::getRoutes()->match(Request::create('/appointments/history', 'GET'));

        $this->assertSame('appointments.history', $route->getName());
        $this->assertSame('/appointments/history', route('appointments.history', absolute: false));
    }

    public function test_history_requires_authentication_active_state_access_and_view_scope(): void
    {
        $this->get('/appointments/history')->assertRedirect(route('login'));

        $inactive = $this->user('employee', 'Inactiva');
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->get('/appointments/history')->assertRedirect(route('login'));

        $withoutAccess = User::factory()->create(['is_active' => true]);
        $withoutAccess->givePermissionTo(Permissions::APPOINTMENTS_VIEW_OWN);
        $this->actingAs($withoutAccess)->get('/appointments/history')->assertForbidden();

        $withoutScope = User::factory()->create(['is_active' => true]);
        $withoutScope->givePermissionTo(Permissions::APPOINTMENTS_ACCESS);
        $this->actingAs($withoutScope)->get('/appointments/history')->assertForbidden();
    }

    public function test_owner_receives_all_four_statuses_in_a_dedicated_read_only_dto(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Ana');
        foreach ([
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELED,
            Appointment::STATUS_NO_SHOW,
        ] as $index => $status) {
            $this->appointment($owner, "2026-07-2{$index} 15:00:00", $status, "Clienta {$index}", [
                $this->line($employee, "Servicio {$index}", '100.00'),
            ]);
        }

        $this->actingAs($owner)->get('/appointments/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/History')
                ->has('appointments.data', 4)
                ->where('appointments.data.0.status', Appointment::STATUS_NO_SHOW)
                ->where('appointments.data.1.status', Appointment::STATUS_CANCELED)
                ->where('appointments.data.2.status', Appointment::STATUS_COMPLETED)
                ->where('appointments.data.3.status', Appointment::STATUS_SCHEDULED)
                ->missing('appointments.data.0.can_reschedule')
                ->missing('appointments.data.0.can_change_status')
                ->where('canViewAll', true));
    }

    public function test_date_status_employee_client_and_deleted_service_snapshot_filters_combine(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Ana');
        $other = $this->user('employee', 'Bea');
        $match = $this->appointment($owner, '2026-07-15 16:00:00', Appointment::STATUS_CANCELED, '  María Histórica  ', [
            $this->line($employee, 'Esmaltado eliminado', '125.00'),
        ]);
        $this->appointment($owner, '2026-07-15 17:00:00', Appointment::STATUS_CANCELED, 'María Histórica', [$this->line($other, 'Esmaltado eliminado')]);
        $this->appointment($owner, '2026-07-15 18:00:00', Appointment::STATUS_COMPLETED, 'María Histórica', [$this->line($employee, 'Esmaltado eliminado')]);
        $this->appointment($owner, '2026-07-16 18:00:00', Appointment::STATUS_CANCELED, 'Otra clienta', [$this->line($employee, 'Otro servicio')]);

        $query = http_build_query([
            'date_from' => '2026-07-15',
            'date_to' => '2026-07-15',
            'status' => 'canceled',
            'employee_id' => $employee->id,
            'client' => '  María  ',
            'service' => '  eliminado  ',
        ]);
        $this->actingAs($owner)->get("/appointments/history?{$query}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments.data', 1)
                ->where('appointments.data.0.id', $match->id)
                ->where('appointments.data.0.visible_services.0.name', 'Esmaltado eliminado')
                ->where('filters.client', 'María')
                ->where('filters.service', 'eliminado'));
    }

    public function test_honduras_dates_use_inclusive_local_days_and_limit_ranges_to_366_days(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Ana');
        $before = $this->appointment($owner, '2026-07-01 05:59:59', Appointment::STATUS_SCHEDULED, 'Antes', [$this->line($employee, 'Uno')]);
        $start = $this->appointment($owner, '2026-07-01 06:00:00', Appointment::STATUS_SCHEDULED, 'Inicio', [$this->line($employee, 'Dos')]);
        $end = $this->appointment($owner, '2026-07-02 05:59:59', Appointment::STATUS_SCHEDULED, 'Final', [$this->line($employee, 'Tres')]);
        $after = $this->appointment($owner, '2026-07-02 06:00:00', Appointment::STATUS_SCHEDULED, 'Después', [$this->line($employee, 'Cuatro')]);

        $response = $this->actingAs($owner)->get('/appointments/history?date_from=2026-07-01&date_to=2026-07-01');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('appointments.data', 2)
            ->where('appointments.data.0.id', $end->id)
            ->where('appointments.data.1.id', $start->id));
        $response->assertDontSee('Antes')->assertDontSee('Después');
        $this->assertNotSame($before->id, $start->id);
        $this->assertNotSame($after->id, $end->id);

        $this->from('/appointments/history')->get('/appointments/history?date_from=2025-01-01&date_to=2026-01-02')
            ->assertSessionHasErrors('date_to');
        $this->get('/appointments/history?date_from=2026-07-02&date_to=2026-07-01')
            ->assertSessionHasErrors('date_to');
        $this->get('/appointments/history?date_from=2025-01-01&date_to=2026-01-01')->assertOk();
    }

    public function test_employee_gets_only_own_segments_of_shared_appointments_and_no_unrelated_records(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Propia');
        $other = $this->user('employee', 'Nombre ajeno confidencial');
        $shared = $this->appointment($owner, '2026-07-20 15:00:00', Appointment::STATUS_SCHEDULED, 'Clienta compartida', [
            $this->line($employee, 'Servicio propio', '100.00'),
            $this->line($other, 'Servicio ajeno confidencial', '999.00'),
        ]);
        $this->appointment($owner, '2026-07-20 17:00:00', Appointment::STATUS_SCHEDULED, 'Clienta ajena confidencial', [
            $this->line($other, 'Solo ajeno', '500.00'),
        ]);

        $response = $this->actingAs($employee)->get('/appointments/history');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.id', $shared->id)
            ->has('appointments.data.0.visible_services', 1)
            ->where('appointments.data.0.visible_services.0.name', 'Servicio propio')
            ->where('appointments.data.0.visible_total', '100.00')
            ->missing('appointments.data.0.personnel')
            ->has('assignees', 0)
            ->where('canViewAll', false));
        $response->assertDontSee('Servicio ajeno confidencial')
            ->assertDontSee('Nombre ajeno confidencial')
            ->assertDontSee('Clienta ajena confidencial')
            ->assertDontSee('999.00');
    }

    public function test_employee_service_filter_cannot_infer_another_segment_and_fabricated_employee_is_rejected(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Propia');
        $other = $this->user('employee', 'Ajena');
        $this->appointment($owner, '2026-07-20 15:00:00', Appointment::STATUS_SCHEDULED, 'Compartida', [
            $this->line($employee, 'Propio'),
            $this->line($other, 'Secreto especial'),
        ]);

        $this->actingAs($employee)->get('/appointments/history?service=Secreto')
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 0));
        $this->from('/appointments/history')->get("/appointments/history?employee_id={$other->id}")
            ->assertSessionHasErrors(['employee_id' => 'No puedes filtrar el historial de otra persona.']);
        $this->get("/appointments/history?employee_id={$employee->id}")->assertOk();
    }

    public function test_pagination_preserves_filters_and_returns_no_unrelated_page_data(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Ana');
        foreach (range(1, 21) as $index) {
            $this->appointment($owner, '2026-07-15 16:00:00', Appointment::STATUS_CANCELED, "Filtro {$index}", [$this->line($employee, 'Snapshot')]);
        }
        $this->appointment($owner, '2026-07-15 17:00:00', Appointment::STATUS_COMPLETED, 'No relacionada', [$this->line($employee, 'Snapshot')]);

        $query = 'status=canceled&client=Filtro&service=Snapshot';
        $this->actingAs($owner)->get("/appointments/history?{$query}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments.data', 20)
                ->where('appointments.meta.current_page', 1)
                ->where('appointments.meta.last_page', 2)
                ->where('appointments.meta.total', 21)
                ->where('appointments.links.next', fn ($url) => is_string($url)
                    && str_contains($url, 'status=canceled')
                    && str_contains($url, 'client=Filtro')
                    && str_contains($url, 'service=Snapshot')));
        $this->get("/appointments/history?{$query}&page=2")
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments.data', 1)
                ->where('appointments.meta.current_page', 2));
    }

    public function test_deposit_and_sale_summaries_follow_financial_and_receipt_privacy(): void
    {
        $owner = $this->user('owner', 'Dueña');
        $employee = $this->user('employee', 'Propia');
        $seller = $this->user('employee', 'Cajera');
        $shared = $this->appointment($owner, '2026-07-20 15:00:00', Appointment::STATUS_COMPLETED, 'Con venta ajena', [
            $this->line($employee, 'Propio', '100.00'),
            $this->line($seller, 'Ajeno', '200.00'),
        ]);
        $this->deposit($shared, $owner, '75.00');
        $foreignSale = $this->sale($shared, $seller, '300.00');

        $ownSaleAppointment = $this->appointment($owner, '2026-07-21 15:00:00', Appointment::STATUS_COMPLETED, 'Con venta propia', [
            $this->line($employee, 'Propio', '80.00'),
        ]);
        $ownSale = $this->sale($ownSaleAppointment, $employee, '80.00');

        $this->actingAs($owner)->get('/appointments/history?client=Con venta ajena')
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.deposit.amount', '75.00')
                ->where('appointments.data.0.deposit.available_amount', '75.00')
                ->where('appointments.data.0.linked_sale.sale_number', $foreignSale->sale_number));

        $this->actingAs($employee)->get('/appointments/history?client=Con venta ajena')
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.deposit.status_label', 'Pendiente de aplicar')
                ->missing('appointments.data.0.deposit.amount')
                ->missing('appointments.data.0.deposit.available_amount')
                ->where('appointments.data.0.linked_sale', null));
        $this->getJson("/appointments/{$shared->id}")
            ->assertOk()
            ->assertJsonPath('appointment.deposit.amount', '75.00')
            ->assertJsonPath('appointment.deposit.estimated_balance', '0.00')
            ->assertJsonMissingPath('appointment.deposit.available_amount');

        $scheduledShared = $this->appointment($owner, '2026-07-22 15:00:00', Appointment::STATUS_SCHEDULED, 'Saldo compartido', [
            $this->line($employee, 'Propio programado', '100.00'),
            $this->line($seller, 'Ajeno programado', '200.00'),
        ]);
        $this->deposit($scheduledShared, $owner, '75.00');
        $this->getJson("/appointments/{$scheduledShared->id}")
            ->assertOk()
            ->assertJsonPath('appointment.deposit.estimated_balance', '75.00')
            ->assertJsonMissingPath('appointment.deposit.available_amount');
        $this->get('/appointments/history?client=Con venta propia')
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.linked_sale.sale_number', $ownSale->sale_number)
                ->where('appointments.data.0.linked_sale.receipt_url', route('sales.receipt', $ownSale)));
    }

    public function test_terminal_history_has_no_mutation_contract_and_detail_keeps_snapshots_completion_and_safe_audit(): void
    {
        $owner = $this->user('owner', 'Responsable');
        $employee = $this->user('employee', 'Ana');
        $appointment = $this->appointment($owner, '2026-07-20 15:00:00', Appointment::STATUS_COMPLETED, 'Clienta auditada', [
            $this->line($employee, 'Snapshot eliminado', '145.00', 45),
        ]);
        AppointmentEvent::query()->forceCreate([
            'appointment_id' => $appointment->id,
            'type' => AppointmentEvent::TYPE_COMPLETED,
            'previous_values' => ['status' => Appointment::STATUS_SCHEDULED],
            'new_values' => ['status' => Appointment::STATUS_COMPLETED],
            'performed_by' => $owner->id,
            'occurred_at' => '2026-07-20 17:00:00',
        ]);
        $sale = $this->sale($appointment, $owner, '145.00');

        $this->actingAs($owner)->get('/appointments/history?status=completed')
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.completed_at_display', '20 de julio de 2026, 11:00 a. m.')
                ->where('appointments.data.0.linked_sale.sale_number', $sale->sale_number)
                ->missing('appointments.data.0.can_checkout')
                ->missing('appointments.data.0.actions'));
        $this->getJson("/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('appointment.status', Appointment::STATUS_COMPLETED)
            ->assertJsonPath('appointment.completed_at_display', '20 de julio de 2026, 11:00 a. m.')
            ->assertJsonPath('appointment.visible_items.0.service_name', 'Snapshot eliminado')
            ->assertJsonPath('appointment.visible_items.0.duration_minutes', 45)
            ->assertJsonPath('appointment.visible_items.0.assigned_to.name', 'Ana')
            ->assertJsonPath('appointment.linked_sale.sale_number', $sale->sale_number)
            ->assertJsonPath('appointment.events.0.type_label', 'Cita atendida y cobrada')
            ->assertJsonPath('appointment.events.0.changes.0.new', 'Completada')
            ->assertJsonMissing(['previous_values'])
            ->assertJsonMissing(['new_values']);
    }

    private function appointment(User $actor, string $start, string $status, string $client, array $lines): Appointment
    {
        $startAt = Carbon::parse($start, 'UTC');
        $duration = collect($lines)->sum(fn ($line) => $line['duration_minutes'] * $line['quantity']);
        $total = collect($lines)->sum(fn ($line) => (int) round(((float) $line['line_total']) * 100));
        $appointment = Appointment::query()->forceCreate([
            'client_name' => $client,
            'client_phone' => '9999-9999',
            'assigned_to' => $lines[0]['employee']->id,
            'scheduled_start' => $startAt,
            'scheduled_end' => $startAt->copy()->addMinutes($duration),
            'expected_total' => number_format($total / 100, 2, '.', ''),
            'expected_duration_minutes' => $duration,
            'status' => $status,
            'notes' => 'Nota histórica segura',
            'created_by' => $actor->id,
            'completed_at' => $status === Appointment::STATUS_COMPLETED ? '2026-07-20 17:00:00' : null,
            'canceled_at' => $status === Appointment::STATUS_CANCELED ? '2026-07-20 17:00:00' : null,
            'canceled_by' => $status === Appointment::STATUS_CANCELED ? $actor->id : null,
            'cancellation_reason' => $status === Appointment::STATUS_CANCELED ? 'Cancelación histórica.' : null,
            'no_show_at' => $status === Appointment::STATUS_NO_SHOW ? '2026-07-20 17:00:00' : null,
            'no_show_by' => $status === Appointment::STATUS_NO_SHOW ? $actor->id : null,
            'no_show_reason' => $status === Appointment::STATUS_NO_SHOW ? 'Ausencia histórica.' : null,
        ]);
        $segmentStart = $startAt->copy();
        foreach ($lines as $index => $line) {
            $segmentEnd = $segmentStart->copy()->addMinutes($line['duration_minutes'] * $line['quantity']);
            AppointmentItem::query()->forceCreate([
                'appointment_id' => $appointment->id,
                'service_id' => null,
                'service_name' => $line['service_name'],
                'service_description' => 'Snapshot histórico',
                'duration_minutes' => $line['duration_minutes'],
                'default_duration_minutes' => $line['duration_minutes'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
                'assigned_to' => $line['employee']->id,
                'position' => $index + 1,
                'scheduled_start' => $segmentStart,
                'scheduled_end' => $segmentEnd,
            ]);
            $segmentStart = $segmentEnd;
        }

        return $appointment;
    }

    private function line(User $employee, string $service, string $total = '100.00', int $duration = 30, int $quantity = 1): array
    {
        return [
            'employee' => $employee,
            'service_name' => $service,
            'duration_minutes' => $duration,
            'quantity' => $quantity,
            'unit_price' => number_format(((float) $total) / $quantity, 2, '.', ''),
            'line_total' => $total,
        ];
    }

    private function deposit(Appointment $appointment, User $actor, string $amount): AppointmentDeposit
    {
        return AppointmentDeposit::query()->forceCreate([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'payment_method' => AppointmentDeposit::PAYMENT_METHOD_CASH,
            'card_fee_rate' => '0.00',
            'card_fee_amount' => '0.00',
            'net_amount' => $amount,
            'status' => AppointmentDeposit::STATUS_PENDING,
            'paid_at' => '2026-07-20 14:00:00',
            'recorded_by' => $actor->id,
            'applied_amount' => '0.00',
            'refunded_amount' => '0.00',
            'retained_amount' => '0.00',
        ]);
    }

    private function sale(Appointment $appointment, User $seller, string $total): Sale
    {
        return Sale::query()->forceCreate([
            'appointment_id' => $appointment->id,
            'sale_number' => 'SL-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT),
            'sold_by' => $seller->id,
            'sold_at' => '2026-07-20 17:00:00',
            'subtotal' => $total,
            'total' => $total,
            'total_services' => 1,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_METHOD_CASH,
            'card_fee_rate' => '0.00',
            'card_fee_amount' => '0.00',
            'net_amount' => $total,
            'checkout_token' => (string) Str::uuid(),
            'request_hash' => hash('sha256', (string) Str::uuid()),
        ]);
    }

    private function user(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
