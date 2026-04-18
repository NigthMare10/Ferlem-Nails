<?php

namespace App\Modules\Agenda\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\Agenda\Models\Cita;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgendaService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function crear(array $payload, Sucursal $sucursal, User $user, ?Request $request = null): Cita
    {
        abort_unless($user->sucursales()->whereKey($sucursal->id)->exists(), 403, 'No puedes operar sobre una sucursal no asignada.');

        if (! empty($payload['empleado_id'])) {
            $employee = Empleado::query()->findOrFail($payload['empleado_id']);
            $service = Servicio::query()->findOrFail($payload['servicio_id']);

            if (! $employee->sucursales()->where('sucursal_id', $sucursal->id)->exists()) {
                throw ValidationException::withMessages([
                    'empleado_public_id' => 'El empleado seleccionado no pertenece a la sucursal activa.',
                ]);
            }

            if (! $employee->servicios()->where('servicio_id', $service->id)->exists()) {
                throw ValidationException::withMessages([
                    'empleado_public_id' => 'El empleado seleccionado no está habilitado para el servicio indicado.',
                ]);
            }
        }

        $appointment = Cita::create([
            'sucursal_id' => $sucursal->id,
            'cliente_id' => $payload['cliente_id'],
            'perfil_cliente_id' => $payload['perfil_cliente_id'] ?? null,
            'empleado_id' => $payload['empleado_id'] ?? null,
            'servicio_id' => $payload['servicio_id'],
            'created_by_user_id' => $user->id,
            'scheduled_start' => $payload['scheduled_start'],
            'scheduled_end' => $payload['scheduled_end'],
            'status' => $payload['status'] ?? 'programada',
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->auditService->log(
            action: 'agenda.cita_creada',
            actor: $user,
            branchId: $sucursal->id,
            description: 'Se creó una cita en la agenda de la sucursal activa.',
            auditable: $appointment,
            request: $request,
        );

        return $appointment;
    }
}
