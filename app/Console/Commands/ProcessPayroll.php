<?php

namespace App\Console\Commands;

use App\Actions\Payroll\ProcessPayrollAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ProcessPayroll extends Command
{
    protected $signature = 'studio:process-payroll {--date= : Fecha de corte YYYY-MM-DD} {--dry-run : Muestra cuotas sin escribir}';

    protected $description = 'Procesa automáticamente las cuotas salariales vencidas';

    public function handle(ProcessPayrollAction $action): int
    {
        try {
            $date = $this->option('date')
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), 'America/Tegucigalpa')
                : CarbonImmutable::now('America/Tegucigalpa')->startOfDay();
            if (! $date || $date->format('Y-m-d') !== ($this->option('date') ?: $date->format('Y-m-d'))) {
                throw new \InvalidArgumentException;
            }
        } catch (Throwable) {
            $this->error('La fecha debe usar el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        $actor = User::query()->where('is_active', true)->role('owner')->orderBy('id')->first();
        $results = $action->execute($date, $actor, (bool) $this->option('dry-run'));
        $this->table(['Empleado', 'Fecha', 'Cuota', 'Monto', 'Resultado'], $results->map(fn (array $row) => [
            $row['employee'], $row['scheduled_date'], $row['installment'] === 'first' ? 'Día 15' : 'Fin de mes', 'L '.$row['amount'], $row['status'],
        ]));
        $errors = $results->where('status', 'error')->count();
        $this->info("Procesadas: {$results->count()}; incidencias: {$errors}.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
