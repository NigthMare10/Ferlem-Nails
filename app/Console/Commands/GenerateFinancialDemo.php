<?php

namespace App\Console\Commands;

use App\Actions\Payroll\ConfigureCompensationProfileAction;
use App\Actions\Payroll\ProcessPayrollAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\EmployeeCompensationProfile;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

class GenerateFinancialDemo extends Command
{
    private const EMPLOYEE_EMAIL = 'employee.financial.demo@studio-lemus.local';

    protected $signature = 'studio:generate-financial-demo
        {--months=2 : Meses completos que se utilizarán}
        {--sales=20 : Cantidad de ventas demo}
        {--force : Ejecuta sin confirmación interactiva}';

    protected $description = 'Genera datos financieros demostrativos solo en local o testing';

    public function handle(ConfigureCompensationProfileAction $configure, CreateSaleAction $createSale, ProcessPayrollAction $processPayroll): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo puede ejecutarse en APP_ENV=local o testing.');

            return self::FAILURE;
        }
        $months = filter_var($this->option('months'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        $salesCount = filter_var($this->option('sales'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        if (! $months || ! $salesCount) {
            $this->error('Meses debe estar entre 1 y 12 y ventas entre 1 y 200.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("¿Crear {$salesCount} ventas y {$months} meses de datos demo locales?")) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        $owner = User::query()->where('is_active', true)->role('owner')->orderBy('id')->first();
        $services = Service::query()->where('is_active', true)->orderBy('id')->get();
        if (! $owner || $services->isEmpty()) {
            $this->error('Se requiere un propietario activo y al menos un servicio activo.');

            return self::FAILURE;
        }

        $lastMonth = CarbonImmutable::now('America/Tegucigalpa')->startOfMonth()->subMonth();
        $firstMonth = $lastMonth->subMonths($months - 1);
        try {
            [$employee, $temporaryPassword] = $this->employee($owner, $firstMonth, $configure);
            $this->sales($employee, $services, $firstMonth, $lastMonth, $salesCount, $createSale);
            for ($month = $firstMonth; $month->lte($lastMonth); $month = $month->addMonth()) {
                $processPayroll->execute($month->setDay(15), $owner);
                $processPayroll->execute($month->endOfMonth(), $owner);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se pudieron completar los datos demo: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Datos financieros demo disponibles para revisión visual.');
        $this->line('Empleado: '.$employee->name.' <'.$employee->email.'>');
        if ($temporaryPassword) {
            $this->warn('Credencial temporal local (se muestra una sola vez): '.$temporaryPassword);
        } else {
            $this->line('El usuario demo ya existía; su contraseña no fue modificada ni mostrada.');
        }
        $this->line('Ventas demo: '.Sale::query()->where('client_name', 'like', 'Demo financiero %')->count());

        return self::SUCCESS;
    }

    private function employee(User $owner, CarbonImmutable $contractStart, ConfigureCompensationProfileAction $configure): array
    {
        $employee = User::query()->where('email', self::EMPLOYEE_EMAIL)->first();
        $password = null;
        if (! $employee) {
            $password = Str::password(20);
            $employee = DB::transaction(function () use ($password): User {
                $user = User::create(['name' => 'Empleado Demo Financiero', 'email' => self::EMPLOYEE_EMAIL, 'password' => $password, 'is_active' => true]);
                $user->assignRole('employee');

                return $user;
            });
        }

        $matches = EmployeeCompensationProfile::query()->where('user_id', $employee->id)
            ->where('monthly_salary', '15000.00')->where('contract_start_date', $contractStart->toDateString())
            ->where('is_indefinite', true)->where('default_payment_method', 'transfer')
            ->where('auto_generate_payroll_expense', true)->exists();
        if (! $matches) {
            $configure->execute($owner, $employee, [
                'monthly_salary' => '15000.00', 'effective_from' => $contractStart->toDateString(), 'effective_to' => null,
                'contract_start_date' => $contractStart->toDateString(), 'contract_end_date' => null, 'is_indefinite' => true,
                'default_payment_method' => 'transfer', 'auto_generate_payroll_expense' => true, 'notes' => 'Perfil demostrativo local.',
            ]);
        }

        return [$employee, $password];
    }

    private function sales(User $employee, $services, CarbonImmutable $firstMonth, CarbonImmutable $lastMonth, int $count, CreateSaleAction $action): void
    {
        $days = max(1, $firstMonth->diffInDays($lastMonth->endOfMonth()));
        for ($index = 1; $index <= $count; $index++) {
            $date = $firstMonth->addDays((int) floor((($index - 1) * $days) / max(1, $count - 1)))->setTime(9 + ($index % 8), ($index * 7) % 60);
            $method = match ($index % 3) {
                1 => Sale::PAYMENT_METHOD_CASH, 2 => Sale::PAYMENT_METHOD_CARD, default => Sale::PAYMENT_METHOD_TRANSFER
            };
            $first = $services[($index - 1) % $services->count()];
            $items = [['service_id' => $first->id, 'quantity' => ($index % 4 === 0) ? 2 : 1]];
            if ($services->count() > 1 && $index % 5 === 0) {
                $second = $services[$index % $services->count()];
                if ($second->id !== $first->id) {
                    $items[] = ['service_id' => $second->id, 'quantity' => 1];
                }
            }

            CarbonImmutable::setTestNow($date->utc());
            try {
                $action->execute($employee, $items, Uuid::uuid5(Uuid::NAMESPACE_URL, sprintf('studio-lemus:financial-demo:v1:sale:%03d', $index))->toString(), $method, null, sprintf('Demo financiero %03d', $index));
            } finally {
                CarbonImmutable::setTestNow();
            }
        }
    }
}
