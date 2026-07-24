<?php

namespace App\Actions\Reports;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;

final class BuildAppointmentProjectionAction
{
    public function execute(array $filters): array
    {
        [$localStart, $localEnd, $referenceDate] = ReportPeriod::bounds($filters);
        $period = $filters['period'] ?? 'today';
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        $appointments = Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('scheduled_start', '>=', $localStart->utc())
            ->where('scheduled_start', '<', $localEnd->utc())
            ->when($employeeId !== null, fn (Builder $query) => $query->whereHas(
                'items', fn (Builder $items) => $items->where('assigned_to', $employeeId),
            ))
            ->with([
                'items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
                'items.assignedTo:id,name',
                'deposit',
            ])
            ->get();

        $appointmentCount = $appointments->count();
        $servicesCount = 0;
        $grossCents = 0;
        $depositsReceivedCents = 0;
        $pendingBalanceCents = 0;
        $employees = [];

        foreach ($appointments as $appointment) {
            $allItems = $appointment->items;
            $selectedItems = $employeeId === null
                ? $allItems
                : $allItems->where('assigned_to', $employeeId);
            $availableDepositCents = $appointment->deposit?->availableAmountCents() ?? 0;
            $availableAllocations = $this->allocateDeposit($allItems->all(), $availableDepositCents);
            $receivedDepositCents = $appointment->deposit?->status === AppointmentDeposit::STATUS_PENDING
                ? Money::toCents($appointment->deposit->amount)
                : 0;
            $receivedAllocations = $this->allocateDeposit($allItems->all(), $receivedDepositCents);

            if ($employeeId === null) {
                $appointmentGrossCents = Money::toCents($appointment->expected_total);
                $appointmentDepositCents = $availableDepositCents;
                $appointmentReceivedCents = $receivedDepositCents;
            } else {
                $appointmentGrossCents = $selectedItems->sum(fn ($item) => Money::toCents($item->line_total));
                $appointmentDepositCents = $selectedItems->sum(fn ($item) => $availableAllocations[$item->id] ?? 0);
                $appointmentReceivedCents = $selectedItems->sum(fn ($item) => $receivedAllocations[$item->id] ?? 0);
            }

            $servicesCount += $selectedItems->sum('quantity');
            $grossCents += $appointmentGrossCents;
            $depositsReceivedCents += $appointmentReceivedCents;
            $pendingBalanceCents += max(0, $appointmentGrossCents - $appointmentDepositCents);

            foreach ($selectedItems as $item) {
                $id = (int) $item->assigned_to;
                $employees[$id] ??= [
                    'id' => $id,
                    'name' => $item->assignedTo->name,
                    'appointment_ids' => [],
                    'services_count' => 0,
                    'gross_cents' => 0,
                    'pending_cents' => 0,
                ];
                $lineCents = Money::toCents($item->line_total);
                $employees[$id]['appointment_ids'][$appointment->id] = true;
                $employees[$id]['services_count'] += $item->quantity;
                $employees[$id]['gross_cents'] += $lineCents;
                $employees[$id]['pending_cents'] += max(0, $lineCents - ($availableAllocations[$item->id] ?? 0));
            }
        }

        return [
            'filters' => [
                'period' => $period,
                'mode' => $filters['mode'] ?? 'projection',
                'date' => $period === 'custom' ? null : $referenceDate->format('Y-m-d'),
                'date_from' => $period === 'custom' ? $localStart->format('Y-m-d') : null,
                'date_to' => $period === 'custom' ? $localEnd->subDay()->format('Y-m-d') : null,
                'employee_id' => $employeeId,
                'payment_method' => $filters['payment_method'] ?? null,
            ],
            'period' => [
                'label' => ReportPeriod::label($period, $localStart, $localEnd),
                'start_date' => $localStart->format('Y-m-d'),
                'end_date' => $localEnd->subDay()->format('Y-m-d'),
                'timezone' => ReportPeriod::TIMEZONE,
                'week_starts_on' => 'monday',
            ],
            'projection' => [
                'appointments_count' => $appointmentCount,
                'services_count' => $servicesCount,
                'projected_gross' => Money::fromCents($grossCents),
                'deposits_received' => Money::fromCents($depositsReceivedCents),
                'pending_balance' => Money::fromCents($pendingBalanceCents),
            ],
            'projection_employees' => collect($employees)->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'appointments_count' => count($row['appointment_ids']),
                'services_count' => $row['services_count'],
                'projected_gross' => Money::fromCents($row['gross_cents']),
                'pending_balance' => Money::fromCents($row['pending_cents']),
            ])->values()->all(),
        ];
    }

    private function allocateDeposit(array $items, int $depositCents): array
    {
        $totalCents = array_sum(array_map(fn ($item) => Money::toCents($item->line_total), $items));
        if ($depositCents === 0 || $totalCents === 0) {
            return array_fill_keys(array_map(fn ($item) => $item->id, $items), 0);
        }

        $allocations = [];
        $used = 0;
        $last = count($items) - 1;
        foreach ($items as $index => $item) {
            $lineCents = Money::toCents($item->line_total);
            $value = $index === $last
                ? $depositCents - $used
                : intdiv(($depositCents * $lineCents) + intdiv($totalCents, 2), $totalCents);
            $value = min($lineCents, $value, $depositCents - $used);
            $allocations[$item->id] = $value;
            $used += $value;
        }

        return $allocations;
    }
}
