<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local')) throw new RuntimeException('Solo respaldo local.');
$tables = ['loyalty_settings', 'loyalty_rewards', 'loyalty_redemptions', 'loyalty_entries'];
$backup = ['engine' => Illuminate\Support\Facades\DB::connection()->getDriverName(), 'at' => now()->toIso8601String()];
foreach ($tables as $table) $backup[$table] = Illuminate\Support\Facades\DB::table($table)->get()->toArray();
$directory = storage_path('app/.old');
if (! is_dir($directory)) mkdir($directory, 0700, true);
$path = $directory.'/loyalty-'.date('Ymd-His').'.json';
file_put_contents($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "Respaldo local de fidelidad: $path\n";
