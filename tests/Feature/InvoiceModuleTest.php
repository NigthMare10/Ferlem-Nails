<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use App\Support\TransferProofStorage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(TransferProofStorage::DISK);
    }

    public function test_guest_is_redirected_and_roles_receive_exact_invoice_scopes(): void
    {
        $this->get('/invoices')->assertRedirect(route('login'));
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $this->assertTrue($owner->can(Permissions::SALES_VIEW_ALL));
        $this->assertTrue($owner->can(Permissions::SALES_VIEW_OWN));
        $this->assertTrue($owner->can(Permissions::SALES_REPRINT));
        $this->assertTrue($owner->can(Permissions::SALES_CANCEL));
        $this->assertTrue($owner->can(Permissions::SALES_UPLOAD_TRANSFER_PROOF));
        $this->assertTrue($owner->can(Permissions::SALES_VIEW_TRANSFER_PROOF));
        $this->assertTrue($administrator->can(Permissions::SALES_VIEW_ALL));
        $this->assertTrue($administrator->can(Permissions::SALES_VIEW_OWN));
        $this->assertTrue($administrator->can(Permissions::SALES_REPRINT));
        $this->assertFalse($administrator->can(Permissions::SALES_CANCEL));
        $this->assertTrue($administrator->can(Permissions::SALES_UPLOAD_TRANSFER_PROOF));
        $this->assertTrue($administrator->can(Permissions::SALES_VIEW_TRANSFER_PROOF));
        $this->assertFalse($employee->can(Permissions::SALES_VIEW_ALL));
        $this->assertTrue($employee->can(Permissions::SALES_VIEW_OWN));
        $this->assertTrue($employee->can(Permissions::SALES_REPRINT));
        $this->assertTrue($employee->can(Permissions::SALES_UPLOAD_TRANSFER_PROOF));
    }

    public function test_invoice_navigation_uses_shared_permissions_and_supports_sidebar_mobile_active_states_and_home_shortcut(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $withoutPermissions = User::factory()->create(['is_active' => true]);

        foreach ([$owner, $administrator, $employee] as $user) {
            $this->assertTrue(
                $user->getAllPermissions()->pluck('name')->contains(Permissions::SALES_VIEW_OWN)
                || $user->getAllPermissions()->pluck('name')->contains(Permissions::SALES_VIEW_ALL),
            );
            $this->actingAs($user)->get('/invoices')->assertOk()->assertInertia(fn (Assert $page) => $page->has('auth.permissions'));
        }
        $this->actingAs($withoutPermissions)->get('/invoices')->assertForbidden();

        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $this->assertStringContainsString("canAny(['sales.view_own', 'sales.view_all'])", $layout);
        $this->assertStringContainsString('mdi-file-document-outline', $layout);
        $this->assertStringContainsString("currentUrl.value.startsWith('/invoices')", $layout);
        $this->assertStringContainsString(':temporary="mobile"', $layout);
        $this->assertStringContainsString('if (mobile.value) drawer.value = false;', $layout);

        $home = file_get_contents(resource_path('js/Pages/Home.vue'));
        $this->assertStringContainsString('Ver facturas', $home);
        $this->assertStringContainsString("canAny(['sales.view_own', 'sales.view_all'])", $home);
    }

    public function test_owner_and_administrator_see_all_while_employee_sees_only_own(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $this->sale($owner, 'SL-OWNER', 'Ana');
        $own = $this->sale($employee, 'SL-EMPLOYEE', 'Beatriz');

        $this->actingAs($owner)->get('/invoices')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 2)->where('auth.navigation.invoices', true));
        $administrator = $this->user('administrator');
        $this->actingAs($administrator)->get('/invoices')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 2));
        $this->actingAs($employee)->get('/invoices')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)->where('invoices.data.0.id', $own->id));
        $this->get(route('invoices.show', Sale::query()->where('sold_by', $owner->id)->firstOrFail()))->assertForbidden();
    }

    public function test_search_date_status_method_employee_and_proof_filters_combine(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $cash = $this->sale($owner, 'SL-CASH', 'María Directa', '2026-07-10 10:00:00');
        $this->payment($cash, 'final_payment', 'cash', '100.00');
        $mixed = $this->sale($employee, 'SL-MIXED', 'Rosa Mixta', '2026-07-11 10:00:00');
        $this->payment($mixed, 'deposit_applied', 'card', '40.00');
        $transfer = $this->payment($mixed, 'final_payment', 'transfer', '60.00');

        $this->actingAs($owner)->get('/invoices?search=Rosa&date_from=2026-07-11&date_to=2026-07-11&status=completed&method=mixed&employee_id='.$employee->id.'&proof_status=pending')
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.sale_number', 'SL-MIXED')
                ->where('invoices.data.0.payment_method_label', 'Mixto')
                ->where('invoices.data.0.proof_status_label', 'Sin captura'));
        $this->get('/invoices?search=SL-CASH')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));

        $transfer->proof_path = '2026/07/'.str_repeat('a', 48).'.jpg';
        $transfer->proof_original_name = 'proof.jpg';
        $transfer->proof_mime = 'image/jpeg';
        $transfer->proof_size = 10;
        $transfer->proof_uploaded_by = $owner->id;
        $transfer->proof_uploaded_at = now('UTC');
        $transfer->save();
        $this->get('/invoices?proof_status=with_proof')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));
    }

    public function test_direct_client_name_is_optional_snapshot_and_historical_null_displays_without_name(): void
    {
        $employee = $this->user('employee');
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);
        $this->actingAs($employee)->post('/sales', [
            'checkout_token' => (string) Str::uuid(), 'payment_method' => 'cash',
            'client_name' => '  Lucía Pérez  ', 'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ])->assertStatus(303);
        $this->assertSame('Lucía Pérez', Sale::query()->firstOrFail()->client_name);

        $historical = $this->sale($employee, 'SL-HISTORICAL', null);
        $this->get('/invoices')->assertInertia(fn (Assert $page) => $page
            ->where('invoices.data.0.client_name', 'Sin nombre'));
        $this->assertNull($historical->client_name);
    }

    public function test_detail_and_cancellation_reuse_existing_history_without_deleting_records(): void
    {
        $owner = $this->user('owner');
        $sale = $this->sale($owner, 'SL-CANCEL', 'Carla');
        $payment = $this->payment($sale, 'final_payment', 'cash', '100.00');

        $this->actingAs($owner)->get(route('invoices.show', $sale))->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')->where('invoice.sale_number', 'SL-CANCEL')->missing('invoice.checkout_token'));
        $this->post(route('invoices.cancel', $sale), ['cancellation_reason' => 'Cobro duplicado.'])->assertStatus(303);
        $this->assertSame(Sale::STATUS_CANCELED, $sale->fresh()->status);
        $this->assertDatabaseHas('sale_payments', ['id' => $payment->id]);
        $this->get('/invoices?status=canceled')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)->where('invoices.data.0.status_label', 'Anulada'));
        $this->get('/earnings?period=today&date='.now('America/Tegucigalpa')->format('Y-m-d'))
            ->assertInertia(fn (Assert $page) => $page->where('actual.gross_revenue', '0.00'));
    }

    public function test_post_sale_transfer_proof_is_private_scoped_one_time_and_notified(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $sale = $this->sale($employee, 'SL-TRANSFER', 'Diana');
        $payment = $this->payment($sale, 'final_payment', 'transfer', '100.00');

        $this->actingAs($employee)->post(route('invoices.payments.proof.store', [$sale, $payment]), [
            'payment_proof' => UploadedFile::fake()->image('transferencia.jpg'),
        ])->assertStatus(303);
        $payment->refresh();
        Storage::disk(TransferProofStorage::DISK)->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'dedupe_key' => "sale-payment-proof:{$payment->id}"]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $administrator->id, 'dedupe_key' => "sale-payment-proof:{$payment->id}"]);
        $foreign = $this->user('employee');
        $this->actingAs($foreign)->post(route('invoices.payments.proof.store', [$sale, $payment]), [
            'payment_proof' => UploadedFile::fake()->image('ajena.jpg'),
        ])->assertForbidden();
        $this->actingAs($employee)->from(route('invoices.show', $sale))->post(route('invoices.payments.proof.store', [$sale, $payment]), [
            'payment_proof' => UploadedFile::fake()->image('otra.jpg'),
        ])->assertRedirect(route('invoices.show', $sale))->assertSessionHasErrors('payment_proof');
    }

    public function test_proof_cannot_be_uploaded_to_cash_card_or_foreign_payment_and_view_requires_scope(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');
        $cashSale = $this->sale($employee, 'SL-CASH-PROOF', null);
        $cash = $this->payment($cashSale, 'final_payment', 'cash', '100.00');
        $cardSale = $this->sale($employee, 'SL-CARD-PROOF', null);
        $card = $this->payment($cardSale, 'final_payment', 'card', '100.00');

        foreach ([[$cashSale, $cash], [$cardSale, $card]] as [$sale, $payment]) {
            $this->actingAs($employee)->post(route('invoices.payments.proof.store', [$sale, $payment]), [
                'payment_proof' => UploadedFile::fake()->image('invalid.jpg'),
            ])->assertForbidden();
        }
        $this->actingAs($owner)->post(route('invoices.payments.proof.store', [$cashSale, $card]), [
            'payment_proof' => UploadedFile::fake()->image('foreign.jpg'),
        ])->assertNotFound();
    }

    public function test_pagination_returns_twenty_records_and_mobile_cards_exist(): void
    {
        $owner = $this->user('owner');
        foreach (range(1, 21) as $index) {
            $this->sale($owner, 'SL-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), null);
        }

        $this->actingAs($owner)->get('/invoices')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 20)->where('invoices.meta.total', 21));
        $source = file_get_contents(resource_path('js/Pages/Invoices/Index.vue'));
        $this->assertStringContainsString('invoice-mobile-cards', $source);
        $this->assertStringContainsString('invoice-desktop-table', $source);
    }

    private function sale(User $seller, string $number, ?string $client, ?string $date = null): Sale
    {
        $sale = new Sale;
        $sale->sold_by = $seller->id;
        $sale->sold_at = $date ? Carbon::parse($date, 'America/Tegucigalpa')->utc() : now('UTC');
        $sale->client_name = $client;
        $sale->subtotal = '100.00';
        $sale->total = '100.00';
        $sale->total_services = 1;
        $sale->status = Sale::STATUS_COMPLETED;
        $sale->payment_method = Sale::PAYMENT_METHOD_CASH;
        $sale->card_fee_rate = '0.00';
        $sale->card_fee_amount = '0.00';
        $sale->net_amount = '100.00';
        $sale->checkout_token = (string) Str::uuid();
        $sale->request_hash = hash('sha256', Str::uuid());
        $sale->save();
        $sale->sale_number = $number;
        $sale->save();
        $item = new SaleItem;
        $item->sale_id = $sale->id;
        $item->performed_by = $seller->id;
        $item->service_name = 'Servicio histórico';
        $item->duration_minutes = 30;
        $item->unit_price = '100.00';
        $item->quantity = 1;
        $item->line_total = '100.00';
        $item->save();

        return $sale;
    }

    private function payment(Sale $sale, string $type, string $method, string $amount): SalePayment
    {
        $payment = new SalePayment;
        $payment->sale_id = $sale->id;
        $payment->type = $type;
        $payment->method = $method;
        $payment->amount = $amount;
        $payment->card_fee_rate = $method === 'card' ? '4.00' : '0.00';
        $payment->card_fee_amount = $method === 'card' ? '1.60' : '0.00';
        $payment->net_amount = $method === 'card' ? '38.40' : $amount;
        $payment->save();

        return $payment;
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
