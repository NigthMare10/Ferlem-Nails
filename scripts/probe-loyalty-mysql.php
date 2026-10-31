<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\{User, Customer, Service, LoyaltyReward, LoyaltyEntry, LoyaltyRedemption, LoyaltyGiftBatch};
use App\Actions\Loyalty\{GiftService, LoyaltyService};
use Illuminate\Support\Str;

$mode = $argv[1] ?? '';
if ($mode === 'create-database') {
    if (! $app->environment('local') || DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('Requiere MySQL local.');
    $name = 'loyalty_qa_'.time();
    DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo json_encode(['database' => $name]);
    exit;
}
if (! $app->environment('qa') || DB::connection()->getDriverName() !== 'mysql' || ! preg_match('/^loyalty_qa_\d+$/', DB::connection()->getDatabaseName())) throw new RuntimeException('Solo esquema QA aislado.');
if ($mode === 'fixtures') {
    (new Database\Seeders\DatabaseSeeder)->run();
    $user = User::create(['name' => 'Concurrencia QA', 'email' => 'concurrencia@lemus.test', 'password' => 'LocalTesting123!', 'is_active' => true]);
    $user->assignRole('owner');
    $client = Customer::create(['name' => 'Clienta concurrencia QA', 'email' => 'concurrencia-cliente@lemus.test', 'password' => 'LocalTesting123!']);
    $client->forceFill(['email_verified_at' => now()])->save();
    $service = Service::create(['name' => 'Servicio concurrencia QA', 'price' => 100, 'duration_minutes' => 30, 'is_active' => true, 'loyalty_points' => 10]);
    $reward = LoyaltyReward::create(['name' => $service->name, 'service_id' => $service->id, 'benefit_type' => 'service', 'points_cost' => 50, 'stock' => 1, 'per_customer_limit' => 2, 'is_active' => true]);
    LoyaltyEntry::create(['customer_id' => $client->id, 'type' => 'award', 'points' => 200, 'qualifying_points' => 200, 'source_key' => 'qa-opening']);
    echo json_encode(['user' => $user->id, 'client' => $client->id, 'reward' => $reward->id, 'gift_token' => (string) Str::uuid()]);
    exit;
}
$data = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
if ($mode === 'reserve') {
    try {
        app(LoyaltyService::class)->reserve(Customer::findOrFail($data['client']), LoyaltyReward::findOrFail($data['reward']), (string) Str::uuid());
        echo json_encode(['reserved' => true]);
    } catch (Illuminate\Validation\ValidationException $e) {
        echo json_encode(['reserved' => false, 'reason' => 'stock-or-limit']);
    }
} elseif ($mode === 'gift') {
    $batch = app(GiftService::class)->send(User::findOrFail($data['user']), ['operation_token' => $data['gift_token'], 'customer_ids' => [$data['client']], 'type' => 'points', 'points' => 25, 'reason' => 'Concurrencia real MySQL']);
    echo json_encode(['batch' => $batch->id]);
} elseif ($mode === 'report') {
    echo json_encode(['stock' => LoyaltyReward::findOrFail($data['reward'])->stock, 'reservations' => LoyaltyRedemption::count(), 'gift_batches' => LoyaltyGiftBatch::count(), 'gift_entries' => LoyaltyEntry::where('type', 'gift')->count(), 'balance' => app(LoyaltyService::class)->balance(Customer::findOrFail($data['client']))]);
}
