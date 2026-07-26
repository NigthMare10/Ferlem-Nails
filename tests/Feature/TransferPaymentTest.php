<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\TransferProofStorage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class TransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake(TransferProofStorage::DISK);
    }

    public function test_transfer_without_proof_saves_zero_fee_and_full_net_amount(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->post('/sales', $this->payload())->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $payment = $sale->payments()->firstOrFail();
        $this->assertSame(Sale::PAYMENT_METHOD_TRANSFER, $sale->payment_method);
        $this->assertSame(Sale::PAYMENT_METHOD_TRANSFER, $payment->method);
        $this->assertSame('0.00', $payment->card_fee_amount);
        $this->assertSame($payment->amount, $payment->net_amount);
        $this->assertNull($payment->proof_path);
    }

    public function test_valid_private_proof_is_stored_streamed_and_retained_after_cancellation(): void
    {
        $owner = $this->user('owner');
        $administrator = $this->user('administrator');
        $employee = $this->user('employee');
        $proof = UploadedFile::fake()->image('transferencia.jpg', 900, 1200);

        $this->actingAs($owner)->post('/sales', $this->payload($proof))->assertStatus(303);

        $sale = Sale::query()->firstOrFail();
        $payment = $sale->payments()->firstOrFail();
        $this->assertStringStartsWith(now('America/Tegucigalpa')->format('Y/m').'/', $payment->proof_path);
        Storage::disk(TransferProofStorage::DISK)->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
        $this->get(route('sales.receipt', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('sale.payments.0.method_label', 'Transferencia')
            ->where('sale.payments.0.proof_url', route('sales.payments.proof', [$sale, $payment]))
            ->missing('sale.payments.0.proof_path'));
        $this->get(route('sales.payments.proof', [$sale, $payment]))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->actingAs($administrator)->get(route('sales.payments.proof', [$sale, $payment]))->assertOk();
        $this->actingAs($employee)->get(route('sales.payments.proof', [$sale, $payment]))->assertForbidden();

        $this->actingAs($owner)->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Auditoría de captura.'])->assertStatus(303);
        Storage::disk(TransferProofStorage::DISK)->assertExists($payment->proof_path);
    }

    public function test_png_and_webp_proofs_are_accepted_and_invalid_or_oversized_files_are_rejected(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);

        foreach (['png', 'webp'] as $extension) {
            $this->post('/sales', $this->payload(UploadedFile::fake()->image("captura.{$extension}")))->assertStatus(303);
        }

        $this->from('/sales/new')->post('/sales', $this->payload(UploadedFile::fake()->create('captura.svg', 10, 'image/svg+xml')))
            ->assertSessionHasErrors('payment_proof');
        $this->from('/sales/new')->post('/sales', $this->payload(UploadedFile::fake()->create('captura.jpg', 5121, 'image/jpeg')))
            ->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('sales', 2);
    }

    public function test_nested_route_cannot_read_another_sales_payment(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->post('/sales', $this->payload(UploadedFile::fake()->image('uno.jpg')));
        $first = Sale::query()->firstOrFail();
        $payment = $first->payments()->firstOrFail();
        $this->post('/sales', $this->payload(UploadedFile::fake()->image('dos.jpg')));
        $second = Sale::query()->latest('id')->firstOrFail();

        $this->get(route('sales.payments.proof', [$second, $payment]))->assertNotFound();
    }

    public function test_proof_is_deleted_when_payment_persistence_rolls_back(): void
    {
        $owner = $this->user('owner');
        Event::listen('eloquent.creating: '.SalePayment::class, fn () => throw new RuntimeException('forced rollback'));

        try {
            $this->actingAs($owner)->post('/sales', $this->payload(UploadedFile::fake()->image('rollback.jpg')));
        } finally {
            Event::forget('eloquent.creating: '.SalePayment::class);
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame([], Storage::disk(TransferProofStorage::DISK)->allFiles());
    }

    private function payload(?UploadedFile $proof = null): array
    {
        $service = Service::query()->create([
            'name' => 'Servicio '.Str::random(8),
            'duration_minutes' => 30,
            'price' => '250.00',
            'is_active' => true,
        ]);

        return array_filter([
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => Sale::PAYMENT_METHOD_TRANSFER,
            'payment_proof' => $proof,
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ], fn ($value) => $value !== null);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
