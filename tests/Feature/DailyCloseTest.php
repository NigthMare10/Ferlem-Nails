<?php

namespace Tests\Feature;

use App\Actions\DailyClose\CreateDailyCloseReportAction;
use App\Mail\DailyCloseReportMail;
use App\Models\DailyCloseReport;
use App\Models\DailyCloseSetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\DailyCloseEmailSender;
use App\Services\DailyClosePdfGenerator;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class DailyCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('daily_closures');
        Carbon::setTestNow('2026-07-29 03:00:00 UTC'); // 21:00 in Honduras.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'sandbox.smtp.mailtrap.io',
                'port' => 587,
                'username' => 'mailtrap-user',
                'password' => 'mailtrap-secret',
            ],
            'mail.from.address' => 'reports@studiolemus.test',
            'mail.from.name' => 'Studio Lemus',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_operational_configuration_is_saved_and_mail_credentials_are_not_exposed(): void
    {
        $owner = $this->user('owner');
        $employee = $this->user('employee');

        $this->actingAs($employee)->get('/configuration/daily-close')->assertForbidden();
        $this->actingAs($owner)->put('/configuration/daily-close', $this->settingsPayload([
            'enabled' => true,
            'recipient_emails' => ['owner@example.com'],
            'smtp_host' => 'should-not-be-persisted.test',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(DailyCloseSetting::query()->firstOrFail()->enabled);
        $this->assertSame(['owner@example.com'], DailyCloseSetting::query()->firstOrFail()->recipient_emails);
        $this->assertFalse(Schema::hasColumn('daily_close_settings', 'smtp_host'));
        $this->assertFalse(Schema::hasColumn('daily_close_settings', 'smtp_password'));
        $this->assertDatabaseHas('daily_close_setting_events', ['performed_by' => $owner->id]);

        $this->get('/configuration/daily-close')->assertInertia(fn (Assert $page) => $page
            ->where('setting.recipient_emails', ['owner@example.com'])
            ->missing('setting.smtp_host')
            ->missing('setting.smtp_password')
            ->missing('setting.smtp_username'));
    }

    public function test_one_and_multiple_recipients_are_saved_and_duplicates_are_rejected(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->put('/configuration/daily-close', $this->settingsPayload([
            'recipient_emails' => ['owner@example.com'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['owner@example.com'], DailyCloseSetting::query()->firstOrFail()->recipient_emails);

        $this->put('/configuration/daily-close', $this->settingsPayload([
            'recipient_emails' => ['owner@example.com', 'admin@example.com'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['owner@example.com', 'admin@example.com'], DailyCloseSetting::query()->firstOrFail()->recipient_emails);

        $this->put('/configuration/daily-close', $this->settingsPayload([
            'recipient_emails' => ['OWNER@example.com', 'owner@example.com'],
        ]))->assertSessionHasErrors('recipient_emails.1');
    }

    public function test_test_email_sends_one_private_pdf_per_recipient_without_regenerating_it(): void
    {
        $owner = $this->configuredOwner(['owner@example.com', 'admin@example.com']);
        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldReceive('send')->twice()->andReturn('message-1', 'message-2');

        $this->actingAs($owner)->post('/configuration/daily-close/test')->assertSessionHas('success');

        $reports = DailyCloseReport::query()->where('trigger', DailyCloseReport::TRIGGER_TEST)->get();
        $this->assertCount(2, $reports);
        $this->assertSame(['admin@example.com', 'owner@example.com'], $reports->pluck('recipient_email')->sort()->values()->all());
        $this->assertCount(1, $reports->pluck('pdf_path')->unique());
        $this->assertTrue($reports->every(fn (DailyCloseReport $report) => $report->status === DailyCloseReport::STATUS_SENT));
    }

    public function test_disabled_schedule_does_not_send_and_due_schedule_is_idempotent(): void
    {
        $this->configuredOwner(['owner@example.com']);
        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldReceive('send')->once()->andReturn('scheduled-message');

        $this->artisan('studio:dispatch-daily-close-email')->assertSuccessful();
        $this->assertDatabaseCount('daily_close_reports', 0);

        DailyCloseSetting::query()->firstOrFail()->update(['enabled' => true, 'send_time' => '21:00']);
        $this->artisan('studio:dispatch-daily-close-email')->assertSuccessful();
        $this->artisan('studio:dispatch-daily-close-email')->assertSuccessful();

        $this->assertSame(1, DailyCloseReport::query()->where('trigger', DailyCloseReport::TRIGGER_SCHEDULED)->count());
        $this->assertDatabaseHas('daily_close_reports', [
            'recipient_email' => 'owner@example.com',
            'status' => DailyCloseReport::STATUS_SENT,
            'attempts' => 1,
        ]);
    }

    public function test_manual_command_uses_honduras_date_and_force_creates_an_explicit_resend(): void
    {
        $this->configuredOwner(['owner@example.com']);
        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldReceive('send')->twice()->andReturn('first-message', 'forced-message');

        $this->artisan('studio:send-daily-close-email --date=2026-07-28')->assertSuccessful();
        $this->artisan('studio:send-daily-close-email --date=2026-07-28')->assertSuccessful();
        $this->assertDatabaseCount('daily_close_reports', 1);

        $this->artisan('studio:send-daily-close-email --date=2026-07-28 --force')->assertSuccessful();
        $this->assertDatabaseCount('daily_close_reports', 2);
    }

    public function test_pdf_is_private_downloadable_and_attached_to_the_mailable(): void
    {
        $owner = $this->configuredOwner(['owner@example.com']);
        $employee = $this->user('employee');
        $this->sale($owner, 'SL-009999', '2026-07-29 02:00:00', '100.00', '4.00', '96.00');
        $report = app(CreateDailyCloseReportAction::class)->execute(
            CarbonImmutable::parse('2026-07-28', ReportPeriod::TIMEZONE),
            'owner@example.com',
            DailyCloseReport::TRIGGER_MANUAL,
            $owner,
        );
        $generated = app(DailyClosePdfGenerator::class)->generate($report, $owner, $owner->name);
        $pdf = Storage::disk('daily_closures')->get($generated->pdf_path);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(hash('sha256', $pdf), $generated->pdf_sha256);
        $this->assertSame('100.00', $generated->summary_snapshot['gross_revenue']);
        $mail = new DailyCloseReportMail($generated);
        $mail->assertHasSubject('Cierre diario Studio Lemus — 28/07/2026');
        $mail->assertFrom('reports@studiolemus.test', 'Studio Lemus');
        $mail->assertHasAttachment(Storage::disk('daily_closures')->path($generated->pdf_path), [
            'as' => 'Cierre-Studio-Lemus-2026-07-28.pdf',
            'mime' => 'application/pdf',
        ]);

        $this->actingAs($employee)->get("/daily-close/reports/{$generated->id}/download")->assertForbidden();
        $this->actingAs($owner)->get("/daily-close/reports/{$generated->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get('/daily-close/download?date=2026-07-28')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_failure_marks_every_recipient_failed_and_sends_no_email(): void
    {
        $owner = $this->configuredOwner(['owner@example.com', 'admin@example.com']);
        $pdf = $this->mock(DailyClosePdfGenerator::class);
        $pdf->shouldReceive('generate')->once()->andThrow(new RuntimeException('memory path secret'));
        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldNotReceive('send');

        $this->actingAs($owner)->post('/daily-close/send', ['date' => '2026-07-28'])->assertSessionHas('error');

        $reports = DailyCloseReport::query()->get();
        $this->assertCount(2, $reports);
        $this->assertTrue($reports->every(fn (DailyCloseReport $report) => $report->status === DailyCloseReport::STATUS_FAILED));
        $this->assertTrue($reports->every(fn (DailyCloseReport $report) => $report->pdf_path === null));
        $this->assertStringNotContainsString('secret', $reports->first()->error_message);
    }

    public function test_failed_smtp_delivery_is_sanitized_and_can_be_retried_by_an_authorized_user(): void
    {
        $owner = $this->configuredOwner(['owner@example.com']);
        $employee = $this->user('employee');
        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('password=secret-value'));

        $this->actingAs($owner)->post('/daily-close/send', ['date' => '2026-07-28'])->assertSessionHas('error');
        $report = DailyCloseReport::query()->firstOrFail();
        $this->assertSame(DailyCloseReport::STATUS_FAILED, $report->status);
        $this->assertStringNotContainsString('secret-value', $report->error_message);
        $this->actingAs($employee)->post("/daily-close/reports/{$report->id}/retry")->assertForbidden();

        $sender = $this->mock(DailyCloseEmailSender::class);
        $sender->shouldReceive('send')->once()->andReturn('retry-message');
        $this->actingAs($owner)->post("/daily-close/reports/{$report->id}/retry")->assertSessionHas('success');
        $this->assertDatabaseHas('daily_close_reports', [
            'id' => $report->id,
            'status' => DailyCloseReport::STATUS_SENT,
            'attempts' => 2,
        ]);
    }

    public function test_no_functional_whatsapp_or_meta_references_remain(): void
    {
        $files = collect([
            ...File::allFiles(app_path()),
            ...File::allFiles(resource_path('js')),
            ...File::allFiles(config_path()),
            new \SplFileInfo(base_path('routes/web.php')),
            new \SplFileInfo(base_path('routes/console.php')),
        ]);
        $content = $files->map(fn (\SplFileInfo $file) => file_get_contents($file->getPathname()))->implode("\n");

        $this->assertStringNotContainsString('WhatsApp', $content);
        $this->assertStringNotContainsString('MetaWhatsApp', $content);
        $this->assertStringNotContainsString('recipient_e164', $content);
        $this->assertFileDoesNotExist(app_path('Contracts/WhatsAppProviderInterface.php'));
        $this->assertFileDoesNotExist(app_path('Services/MetaWhatsAppCloudProvider.php'));
    }

    private function configuredOwner(array $recipients): User
    {
        $owner = $this->user('owner');
        $this->actingAs($owner)->put('/configuration/daily-close', $this->settingsPayload([
            'recipient_emails' => $recipients,
        ]))->assertSessionHasNoErrors();

        return $owner;
    }

    private function settingsPayload(array $overrides = []): array
    {
        return [
            'enabled' => false,
            'send_time' => '21:00',
            'recipient_emails' => ['owner@example.com'],
            ...$overrides,
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function sale(User $employee, string $number, string $soldAt, string $gross, string $fee, string $net): Sale
    {
        $sale = Sale::query()->forceCreate([
            'sale_number' => $number,
            'sold_by' => $employee->id,
            'sold_at' => $soldAt,
            'subtotal' => $gross,
            'total' => $gross,
            'total_services' => 1,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_METHOD_CARD,
            'card_fee_rate' => '4.00',
            'card_fee_amount' => $fee,
            'net_amount' => $net,
            'checkout_token' => (string) Str::uuid(),
            'request_hash' => hash('sha256', $number),
            'client_name' => 'Clienta de prueba',
        ]);
        SaleItem::query()->forceCreate([
            'sale_id' => $sale->id,
            'service_name' => 'Manicura clásica',
            'service_description' => 'Servicio de prueba',
            'duration_minutes' => 45,
            'unit_price' => $gross,
            'quantity' => 1,
            'line_total' => $gross,
            'performed_by' => $employee->id,
            'position' => 1,
            'allocated_card_fee_amount' => $fee,
            'net_line_amount' => $net,
        ]);
        SalePayment::query()->forceCreate([
            'sale_id' => $sale->id,
            'type' => SalePayment::TYPE_FINAL_PAYMENT,
            'method' => Sale::PAYMENT_METHOD_CARD,
            'amount' => $gross,
            'card_fee_rate' => '4.00',
            'card_fee_amount' => $fee,
            'net_amount' => $net,
        ]);

        return $sale;
    }
}
