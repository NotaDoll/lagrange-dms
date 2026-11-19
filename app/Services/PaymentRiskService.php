<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RiskFlag;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PaymentRiskService
{
    public const DEFAULT_THRESHOLDS = [
        'late_payments' => 2,
        'consecutive_months' => 2,
        'average_days' => 5,
        'recent_default_days' => 30,
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly TenantBalanceService $balances,
    ) {}

    public function thresholds(): array
    {
        return array_replace(self::DEFAULT_THRESHOLDS, array_intersect_key($this->settings->getGroup('risk'), self::DEFAULT_THRESHOLDS));
    }

    /**
     * Evaluate the existing four rules without saving flags or sending SMS.
     * With $asOf, evaluate as the proprietor would have seen it on that day: only charges due by then,
     * and a payment made after that day still counts as unpaid. Used for the dashboard's risk trend.
     */
    public function evaluate(Tenant $tenant, ?array $thresholds = null, ?Carbon $asOf = null, ?\Illuminate\Support\Collection $entries = null): array
    {
        $today = $asOf ? $asOf->copy()->startOfDay() : Carbon::parse(Tenant::localDate());
        // $entries: the tenant's billing entries up to today, built once by a caller that evaluates many
        // past dates (the dashboard history); only those due by $today are used, so the result is the same.
        $charges = $this->prepareCharges($entries ?? $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $today->toDateString()));

        return $this->evaluatePrepared($charges, $today, $thresholds ?? $this->thresholds());
    }

    /**
     * Step 1 of a risk check, done once per tenant: reduce the ledger to what the rules need, with
     * dates as day numbers. Callers checking many dates (the dashboard history) reuse the result.
     *
     * @return list<array{due: int, month: int, paid: ?int, recorded: bool, stored: int}>
     */
    public function prepareCharges(\Illuminate\Support\Collection $entries): array
    {
        return $entries
            ->where('category', '!=', Payment::OPENING_BALANCE)
            ->reject(fn (Payment $payment) => $payment->is_advance_credit)
            ->map(fn (Payment $payment) => [
                'due' => self::dayNumber($payment->due_date),
                'month' => $payment->due_date->year * 12 + $payment->due_date->month - 1,
                'paid' => $payment->paid_date ? self::dayNumber($payment->paid_date) : null,
                'recorded' => $payment->hasRecordedPayment(),
                'stored' => (int) $payment->days_overdue,
            ])->values()->all();
    }

    /**
     * Step 2 of a risk check: the four rules as of $asOf. Only charges due by then count, and a
     * payment made after $asOf still counts as unpaid on that day.
     */
    public function evaluatePrepared(array $charges, Carbon $asOf, array $thresholds): array
    {
        $today = self::dayNumber($asOf);
        $sixMonthsAgo = self::dayNumber($asOf->copy()->startOfDay()->subMonthsNoOverflow(6));
        $days = [];
        $lateMonths = [];
        $lastLateDue = null;
        $late6 = 0;

        foreach ($charges as $charge) {
            if ($charge['due'] > $today) {
                continue;
            }
            $paidByThen = $charge['recorded'] && ($charge['paid'] === null || $charge['paid'] <= $today);
            $daysLate = $paidByThen
                ? max(0, $charge['paid'] !== null ? $charge['paid'] - $charge['due'] : $charge['stored'])
                : max(0, $today - $charge['due']);
            $days[] = $daysLate;
            if ($daysLate > 0) {
                $lateMonths[$charge['month']] = true;
                $lastLateDue = max($lastLateDue ?? $charge['due'], $charge['due']);
                $late6 += $charge['due'] >= $sixMonthsAgo ? 1 : 0;
            }
        }

        ksort($lateMonths);
        $streak = 0;
        $consecutive = 0;
        $previous = null;
        foreach (array_keys($lateMonths) as $month) {
            $streak = $previous !== null && $month === $previous + 1 ? $streak + 1 : 1;
            $consecutive = max($consecutive, $streak);
            $previous = $month;
        }

        $metrics = [
            'late_payments_6mo' => $late6,
            'consecutive_late_months' => $consecutive,
            'avg_days_overdue' => round($days === [] ? 0.0 : array_sum($days) / count($days), 1),
            'days_since_last_default' => $lastLateDue === null ? null : $today - $lastLateDue,
        ];
        $rules = [
            'Repeated late payments' => $metrics['late_payments_6mo'] >= (int) $thresholds['late_payments'],
            'Consecutive late months' => $metrics['consecutive_late_months'] >= (int) $thresholds['consecutive_months'],
            'Average days overdue' => $metrics['avg_days_overdue'] > (float) $thresholds['average_days'],
            'Recent default' => $metrics['days_since_last_default'] !== null && $metrics['days_since_last_default'] <= (int) $thresholds['recent_default_days'],
        ];
        $count = count(array_filter($rules));

        return [...$metrics, 'rules' => $rules, 'rules_tripped' => $count, 'risk_level' => $count >= 2 ? 'high' : ($count === 1 ? 'medium' : 'low')];
    }

    /** Whole days since 1970 in the date's own (Philippine) time zone, so date differences are plain subtraction. */
    private static function dayNumber(Carbon $date): int
    {
        return intdiv($date->getTimestamp() + $date->getOffset(), 86400);
    }

    /** Why a tenant is flagged, in plain words (one per rule that tripped), for the dashboard and tenant page. */
    public function reasons(array $evaluation): array
    {
        $plural = fn (int $n, string $word) => $n.' '.Str::plural($word, $n);
        $days = $evaluation['days_since_last_default'];

        return array_values(array_filter([
            $evaluation['rules']['Repeated late payments'] ? $plural($evaluation['late_payments_6mo'], 'late payment').' in 6 months' : null,
            $evaluation['rules']['Consecutive late months'] ? 'Late '.$evaluation['consecutive_late_months'].' months in a row' : null,
            $evaluation['rules']['Average days overdue'] ? 'Pays '.rtrim(rtrim(number_format($evaluation['avg_days_overdue'], 1), '0'), '.').' days late on average' : null,
            $evaluation['rules']['Recent default'] ? ($days === 0 ? 'Late payment due today' : 'Late payment '.$plural($days, 'day').' ago') : null,
        ]));
    }

    public function calculateForTenant(Tenant $tenant): RiskFlag
    {
        $evaluation = $this->evaluate($tenant);
        $flag = RiskFlag::create([
            'tenant_id' => $tenant->id,
            ...array_diff_key($evaluation, array_flip(['rules', 'rules_tripped'])),
            'calculated_at' => now(),
        ]);
        // No SMS here: the proprietor reviews and sends guardian reminders from the Dashboard.

        return $flag;
    }

    public function calculateForAllTenants(): int
    {
        return Tenant::where('status', 'active')->with(['user', 'payments', 'monthModes', 'roomAssignments.bed.room'])->get()
            ->each(fn ($tenant) => $this->calculateForTenant($tenant))->count();
    }
}
