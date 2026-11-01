<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentRevision;
use Illuminate\Http\Request;

class EditAppointmentController extends Controller
{
    public function __invoke(Request $request, Appointment $appointment)
    {
        $actor = $request->user('web');
        $appointment->load('items');
        abort_if($appointment->loyalty_redemption_id, 422, 'Usa Reprogramar para ajustar una cita de premio.');
        abort_unless($actor->can('appointments.edit_services') && ($actor->can('appointments.update_any') || $actor->hasPermissionTo('appointments.view_all') || $appointment->items->every(fn ($i) => $i->assigned_to === $actor->id)), 403);
        abort_unless($appointment->status === 'scheduled', 422);
        $revision = AppointmentRevision::of($appointment);

        $data = [
            'revision' => $revision,
            'appointment' => ['id' => $appointment->id, 'client_name' => $appointment->client_name, 'client_phone' => $appointment->client_phone, 'notes' => $appointment->notes, 'expected_updated_at' => $appointment->updated_at->toISOString(), 'date' => $appointment->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'), 'start_time' => $appointment->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'), 'items' => $appointment->items->map->only(['id', 'service_id', 'service_name', 'unit_price', 'assigned_to', 'quantity', 'duration_minutes'])->values()],
            'services' => Service::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'duration_minutes']),
            'assignees' => User::where('is_active', true)->permission('appointments.perform')->when(! $actor->can('appointments.assign'), fn ($q) => $q->whereKey($actor->id))->orderBy('display_order')->orderBy('name')->get(['id', 'name']),
        ];
        if ($request->expectsJson()) {
            return response()->json($data)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('appointments.index', ['view' => 'day', 'date' => $data['appointment']['date'], 'month' => substr($data['appointment']['date'], 0, 7), 'edit' => $appointment->id]);
    }
}
