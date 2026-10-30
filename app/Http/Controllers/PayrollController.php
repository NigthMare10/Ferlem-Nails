<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\CancelPayrollObligationAction;
use App\Actions\Payroll\MarkPayrollObligationPaidAction;
use App\Http\Requests\MarkPayrollObligationPaidRequest;
use App\Models\PayrollEvent;
use App\Models\PayrollObligation;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permissions::PAYROLL_VIEW), 403);
        $query = PayrollObligation::query()->with(['user:id,name', 'events.performedBy:id,name'])->orderByDesc('scheduled_date');
        if ($request->filled('month')) {
            $query->where('period_year', substr($request->month, 0, 4))->where('period_month', substr($request->month, 5, 2));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('installment')) {
            $query->where('installment', $request->installment);
        }

        return Inertia::render('Payroll/Index', ['obligations' => $query->paginate(20)->through(function (PayrollObligation $obligation) {
            $data = $obligation->toArray();
            $data['history'] = $obligation->events->map(fn (PayrollEvent $event) => ['text' => $this->eventText($obligation, $event), 'performed_by' => $event->performedBy?->name, 'occurred_at' => $event->occurred_at?->setTimezone('America/Tegucigalpa')->translatedFormat('j \\d\\e F \\d\\e Y, h:i a')])->values();

            return $data;
        })->withQueryString(), 'filters' => $request->only('month', 'status', 'user_id', 'installment'), 'capabilities' => ['mark_paid' => $request->user()->can(Permissions::PAYROLL_MARK_PAID), 'cancel' => $request->user()->can(Permissions::PAYROLL_CANCEL_OBLIGATION)]]);
    }

    public function pay(MarkPayrollObligationPaidRequest $request, PayrollObligation $obligation, MarkPayrollObligationPaidAction $action): RedirectResponse
    {
        $action->execute($request->user(), $obligation, $request->safe()->except('attachment'), $request->file('attachment'));

        return back(303)->with('success', 'Pago de nómina confirmado.');
    }

    public function cancel(Request $request, PayrollObligation $obligation, CancelPayrollObligationAction $action): RedirectResponse
    {
        abort_unless($request->user()->can(Permissions::PAYROLL_CANCEL_OBLIGATION), 403);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $action->execute($request->user(), $obligation, $data['cancellation_reason']);

        return back(303)->with('success', 'Obligación cancelada.');
    }

    private function eventText(PayrollObligation $obligation, PayrollEvent $event): string
    {
        $label = $obligation->installment === PayrollObligation::INSTALLMENT_FIRST ? 'Primera quincena' : 'Segunda quincena';
        $period = CarbonImmutable::create($obligation->period_year, $obligation->period_month, 1, 0, 0, 0, 'America/Tegucigalpa')->locale('es')->translatedFormat('F \\d\\e Y');
        $values = $event->new_values ?? [];

        return match ($event->event_type) {
            'obligation.generated' => "{$label} de {$period} generada por L ".number_format((float) $obligation->amount, 2).'.',
            'obligation.paid' => "{$label} de {$period} marcada como pagada por L ".number_format((float) $obligation->amount, 2).' mediante '.match ($values['payment_method'] ?? '') {
                'transfer' => 'transferencia', 'card' => 'tarjeta', default => 'efectivo'
            }.'. Gasto generado: '.($values['expense_number'] ?? 'Sin dato').'.',
            'obligation.canceled' => "{$label} de {$period} cancelada. Motivo: ".($event->notes ?? 'Sin motivo').'.',
            default => 'Cambio de obligación',
        };
    }
}
