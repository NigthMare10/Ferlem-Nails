<?php

namespace App\Console\Commands;

use App\Actions\Appointments\ProcessExpiredAppointmentsAction;
use Illuminate\Console\Command;

class ProcessExpiredAppointments extends Command
{
    protected $signature = 'studio:process-expired-appointments';

    protected $description = 'Notifica y marca automáticamente las citas vencidas sin cobro';

    public function handle(ProcessExpiredAppointmentsAction $action): int
    {
        $result = $action->execute();
        $this->info("Pendientes de cobro notificadas: {$result['pending']}; marcadas No llegó: {$result['expired']}.");

        return self::SUCCESS;
    }
}
