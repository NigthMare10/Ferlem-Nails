<?php

namespace App\Console\Commands;

use App\Actions\Payroll\GeneratePayrollObligationsAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GeneratePayrollObligations extends Command
{
    protected $signature = 'studio:generate-payroll-obligations {--month=} {--dry-run}';

    protected $description = 'Genera obligaciones salariales pendientes de forma idempotente.';

    public function handle(GeneratePayrollObligationsAction $action): int
    {
        $value = $this->option('month') ?: now('America/Tegucigalpa')->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            $this->error('month debe usar YYYY-MM.');

            return self::FAILURE;
        }
        $items = $action->execute(CarbonImmutable::createFromFormat('Y-m', $value, 'America/Tegucigalpa'), null, (bool) $this->option('dry-run'));
        $this->info($this->option('dry-run') ? "Se generarían {$items->count()} obligaciones." : "Se verificaron {$items->count()} obligaciones.");

        return self::SUCCESS;
    }
}
