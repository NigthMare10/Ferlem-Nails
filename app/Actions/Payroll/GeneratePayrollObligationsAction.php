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

class GeneratePayrollObligationsAction
{
    public function __construct(private PublishInternalNotificationAction $notifications) {}

    public function execute(CarbonImmutable $month, ?User $actor = null, bool $dryRun = false): Collection
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $profiles = EmployeeCompensationProfile::query()->where('is_active', true)->where('effective_from', '<=', $end)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))->with('user:id,name,is_active')->get();

        return $profiles->flatMap(function (EmployeeCompensationProfile $profile) use ($month, $actor, $dryRun) {
            if (! $profile->user?->is_active) {
                return collect();
            }

            return collect([[PayrollObligation::INSTALLMENT_FIRST, $month->setDay($profile->first_payment_day)], [PayrollObligation::INSTALLMENT_SECOND, $month->endOfMonth()]])->filter(fn ($entry) => $entry[1]->toDateString() >= $profile->effective_from->toDateString() && (! $profile->effective_to || $entry[1]->toDateString() <= $profile->effective_to->toDateString()))->map(function ($entry) use ($profile, $month, $actor, $dryRun) {
                [$installment, $date] = $entry;
                $cents = Money::toCents($profile->monthly_salary);
                $amount = $installment === PayrollObligation::INSTALLMENT_FIRST ? intdiv($cents, 2) : $cents - intdiv($cents, 2);
                if ($dryRun) {
                    return ['user_id' => $profile->user_id, 'installment' => $installment, 'scheduled_date' => $date->toDateString(), 'amount' => Money::fromCents($amount)];
                }

                return DB::transaction(function () use ($profile, $month, $actor, $installment, $date, $amount) {
                    $obligation = PayrollObligation::query()->where(['user_id' => $profile->user_id, 'period_year' => $month->year, 'period_month' => $month->month, 'installment' => $installment])->first();
                    if (! $obligation) {
                        $obligation = new PayrollObligation;
                        $obligation->user_id = $profile->user_id;
                        $obligation->period_year = $month->year;
                        $obligation->period_month = $month->month;
                        $obligation->installment = $installment;
                        $obligation->compensation_profile_id = $profile->id;
                        $obligation->scheduled_date = $date->toDateString();
                        $obligation->amount = Money::fromCents($amount);
                        $obligation->status = PayrollObligation::STATUS_PENDING;
                        $obligation->generated_at = now('UTC');
                        $obligation->generated_by = $actor?->id;
                        $obligation->save();
                    }
                    if ($obligation->wasRecentlyCreated) {
                        PayrollAudit::record($obligation, 'obligation.generated', $actor, [], ['scheduled_date' => $obligation->scheduled_date->toDateString(), 'amount' => $obligation->amount, 'status' => $obligation->status]);
                        DB::afterCommit(function () use ($actor, $obligation, $profile): void {
                            $installment = $obligation->installment === PayrollObligation::INSTALLMENT_FIRST ? 'primera quincena' : 'segunda quincena';
                            $month = CarbonImmutable::create($obligation->period_year, $obligation->period_month, 1, 0, 0, 0, 'America/Tegucigalpa')->locale('es')->translatedFormat('F \\d\\e Y');
                            $this->notifications->execute(
                                $actor,
                                'payroll.obligation.generated',
                                'Nueva obligación de nómina',
                                'Se generó la '.$installment.' de '.$month.' para '.$profile->user->name.' por L '.number_format((float) $obligation->amount, 2).'.',
                                '/expenses?section=payroll',
                                ['type' => 'payroll_obligation', 'id' => $obligation->id],
                                'payroll-obligation-generated:'.$obligation->id,
                                $obligation->generated_at,
                                Permissions::PAYROLL_VIEW,
                            );
                        });
                    }
                    if (! $obligation->obligation_number) {
                        $obligation->obligation_number = 'NO-'.str_pad((string) $obligation->id, 6, '0', STR_PAD_LEFT);
                        $obligation->save();
                    }

                    return $obligation;
                });
            });
        })->values();
    }
}
