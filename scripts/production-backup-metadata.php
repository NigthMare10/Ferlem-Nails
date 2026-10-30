<?php

// Private CLI helper. Never copy this script to the public document root.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('production') || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Not a production MySQL connection');
}
$tables = ['users', 'services', 'sales', 'sale_items', 'sale_payments', 'appointments', 'appointment_items', 'appointment_deposits', 'expenses', 'payroll_obligations'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = DB::table($table)->count();
}
if (($argv[2] ?? '') === 'counts') {
    echo json_encode($counts, JSON_THROW_ON_ERROR);
    exit;
}
$destination = $argv[2];
$connection = config('database.connections.'.config('database.default'));
$quote = fn ($s) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $s).'"';
$cnf = "[client]\n";
foreach (['host' => 'host', 'port' => 'port', 'username' => 'user', 'password' => 'password'] as $key => $option) {
    if (isset($connection[$key])) {
        $cnf .= $option.'='.$quote($connection[$key])."\n";
    }
}
file_put_contents($destination.'/mysql.cnf', $cnf);
chmod($destination.'/mysql.cnf', 0600);
file_put_contents($destination.'/database-name', $connection['database']);
chmod($destination.'/database-name', 0600);
file_put_contents($destination.'/before-counts.json', json_encode($counts,JSON_THROW_ON_ERROR));
