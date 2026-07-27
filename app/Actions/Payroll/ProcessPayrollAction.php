<?php

namespace App\Actions\Payroll;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\EmployeeCompensationProfile;
use App\Models\PayrollObligation;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollAudit;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

class ProcessPayrollAction
{
    public function __construct(
        private MarkPayrollObligationPaidAction $markPaid,
        private PublishInternalNotificationAction $notifications,
    ) {}

    public function execute(CarbonImmutable $asOf, ?User $actor, bool $dryRun = false): Collection
    {
        $date = $asOf->setTimezone('America/Tegucigalpa')->startOfDay();
        $profiles = EmployeeCompensationProfile::query()
            ->where('auto_generate_payroll_expense', true)
            ->where('effective_from', '<=', $date->toDateString())
            ->where('contract_start_date', '<=', $date->toDateString())
            ->with('user:id,name,is_active')
            ->orderBy('effective_from')
            ->get();

        return $profiles->flatMap(fn (EmployeeCompensationProfile $profile) => $this->dueInstallments($profile, $date))
            ->unique(fn (array $entry) => implode(':', [$entry['profile']->user_id, $entry['year'], $entry['month'], $entry['installment']]))
            ->map(function (array $entry) use ($actor, $date, $dryRun): array {
                if ($dryRun) {
                    return $this->result($entry, 'dry-run');
                }

                $obligation = $this->ensureObligation($entry, $actor);
                if ($obligation->status === PayrollObligation::STATUS_PAID) {
                    return $this->result($entry, 'already-paid', $obligation);
                }
                if ($obligation->status !== PayrollObligation::STATUS_PENDING) {
                    return $this->result($entry, 'skipped', $obligation);
                }

                try {
                    if (! $actor?->is_active) {
                        throw new \RuntimeException('No existe un propietario activo para registrar el gasto automático.');
                    }
                    if (! $entry['profile']->user?->is_active) {
                        throw new \RuntimeException('El empleado está inactivo.');
                    }
                    if (! in_array($entry['profile']->default_payment_method, ['cash', 'card', 'transfer'], true)) {
                        throw new \RuntimeException('Falta un método habitual de pago válido.');
                    }

                    $this->markPaid->execute($actor, $obligation, [
                        'expense_date' => $entry['due_date']->toDateString(),
                        'payment_method' => $entry['profile']->default_payment_method,
                        'checkout_token' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'studio-lemus:payroll:'.$obligation->id)->toString(),
                        'notes' => 'Generado automáticamente por studio:process-payroll.',
                    ]);

                    return $this->result($entry, 'paid', $obligation->fresh());
                } catch (Throwable $exception) {
                    report($exception);
                    $message = Str::limit($exception->getMessage() ?: 'No se pudo procesar el pago.', 1000);
                    $this->recordFailure($obligation, $message, $actor, $date);

                    return $this->result($entry, 'error', $obligation->fresh(), $message);
                }
            })->values();
    }

    private function dueInstallments(EmployeeCompensationProfile $profile, CarbonImmutable $asOf): Collection
    {
        $start = collect([$profile->effective_from, $profile->contract_start_date])->max()->startOfMonth();
        $endDates = collect([$profile->effective_to, $profile->contract_end_date, $asOf])->filter();
        $end = $endDates->min()->startOfMonth();
        $rows = collect();

        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $installments = [
                PayrollObligation::INSTALLMENT_FIRST => $month->setDay(15),
                PayrollObligation::INSTALLMENT_SECOND => $month->endOfMonth()->startOfDay(),
            ];
            foreach ($installments as $installment => $dueDate) {
                if ($dueDate->gt($asOf)
                    || $dueDate->lt($profile->effective_from)
                    || ($profile->effective_to && $dueDate->gt($profile->effective_to))
                    || $dueDate->lt($profile->contract_start_date)
                    || ($profile->contract_end_date && $dueDate->gt($profile->contract_end_date))) {
                    continue;
                }
                $salaryCents = Money::toCents($profile->monthly_salary);
                $amountCents = $installment === PayrollObligation::INSTALLMENT_FIRST
                    ? intdiv($salaryCents, 2)
                    : $salaryCents - intdiv($salaryCents, 2);
                $rows->push([
                    'profile' => $profile,
                    'year' => $month->year,
                    'month' => $month->month,
                    'installment' => $installment,
                    'due_date' => $dueDate,
                    'amount' => Money::fromCents($amountCents),
                ]);
            }
        }

        return $rows;
    }

    private function ensureObligation(array $entry, ?User $actor): PayrollObligation
    {
        return DB::transaction(function () use ($entry, $actor): PayrollObligation {
            $obligation = PayrollObligation::query()->where([
                'user_id' => $entry['profile']->user_id,
                'period_year' => $entry['year'],
                'period_month' => $entry['month'],
                'installment' => $entry['installment'],
            ])->first();
            if (! $obligation) {
                $obligation = new PayrollObligation;
                $obligation->user_id = $entry['profile']->user_id;
                $obligation->period_year = $entry['year'];
                $obligation->period_month = $entry['month'];
                $obligation->installment = $entry['installment'];
                $obligation->compensation_profile_id = $entry['profile']->id;
                $obligation->scheduled_date = $entry['due_date']->toDateString();
                $obligation->amount = $entry['amount'];
                $obligation->status = PayrollObligation::STATUS_PENDING;
                $obligation->generated_at = now('UTC');
                $obligation->generated_by = $actor?->id;
                $obligation->save();
                $obligation->obligation_number = 'NO-'.str_pad((string) $obligation->id, 6, '0', STR_PAD_LEFT);
                $obligation->save();
                PayrollAudit::record($obligation, 'obligation.generated', $actor, [], [
                    'scheduled_date' => $obligation->scheduled_date->toDateString(),
                    'amount' => $obligation->amount,
                    'status' => $obligation->status,
                ]);
                DB::afterCommit(fn () => $this->notifications->execute(
                    $actor,
                    'payroll.obligation.generated',
                    'Nueva obligación de nómina',
                    'Se generó una cuota automática para '.$entry['profile']->user->name.'.',
                    '/expenses?section=payroll',
                    ['type' => 'payroll_obligation', 'id' => $obligation->id],
                    'payroll-obligation-generated:'.$obligation->id,
                    $obligation->generated_at,
                    Permissions::PAYROLL_VIEW,
                ));
            }

            return $obligation;
        }, 3);
    }

    private function recordFailure(PayrollObligation $obligation, string $message, ?User $actor, CarbonImmutable $date): void
    {
        DB::transaction(function () use ($obligation, $message, $actor, $date): void {
            $locked = PayrollObligation::query()->lockForUpdate()->findOrFail($obligation->id);
            $locked->processing_error = $message;
            $locked->processing_failed_at = now('UTC');
            $locked->processing_attempts++;
            $locked->save();
            PayrollAudit::record($locked, 'obligation.processing_failed', $actor, [], ['error' => $message], 'Intento automático del '.$date->format('d/m/Y'));
            DB::afterCommit(fn () => $this->notifications->execute(
                $actor,
                'payroll.processing_failed',
                'Incidencia de nómina',
                'No se pudo generar un gasto salarial automático. Revisa Gastos.',
                '/expenses?section=payroll',
                ['type' => 'payroll_obligation', 'id' => $locked->id],
                'payroll-processing-failed:'.$locked->id.':'.$locked->processing_attempts,
                $locked->processing_failed_at,
                Permissions::PAYROLL_VIEW,
            ));
        }, 3);
    }

    private function result(array $entry, string $status, ?PayrollObligation $obligation = null, ?string $error = null): array
    {
        return [
            'user_id' => $entry['profile']->user_id,
            'employee' => $entry['profile']->user?->name,
            'scheduled_date' => $entry['due_date']->toDateString(),
            'installment' => $entry['installment'],
            'amount' => $entry['amount'],
            'status' => $status,
            'obligation_id' => $obligation?->id,
            'expense_id' => $obligation?->expense_id,
            'error' => $error,
        ];
    }
}
