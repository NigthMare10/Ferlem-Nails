<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Loyalty\RewardAppointmentService;
use App\Models\Appointment;
use App\Models\LoyaltyRedemption;
use Illuminate\Http\Request;

class RewardAppointmentController extends Controller
{
    public function store(Request $request, LoyaltyRedemption $redemption, RewardAppointmentService $service)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'], 'items' => ['required', 'array', 'size:1'], 'items.*.service_id' => ['required', 'integer'], 'items.*.assigned_to' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'in:1'], 'items.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'], 'notes' => ['nullable', 'string', 'max:1000'], 'has_deposit' => ['nullable', 'boolean']]);
        $a = $service->schedule($request->user('web'), $redemption, $data);
        $date = $a->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d');

        return redirect()->route('appointments.index', ['view' => 'day', 'date' => $date, 'month' => substr($date, 0, 7)], 303)->with('success', 'Cita gratuita de premio agendada. No se generará ningún cobro.');
    }

    public function complete(Request $request, Appointment $appointment, RewardAppointmentService $service)
    {
        abort_unless($appointment->loyalty_redemption_id, 404);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $service->complete($request->user('web'), $appointment);

        return back()->with('success', 'Premio atendido y entregado. Sin cobro ni puntos por compra.');
    }
}
