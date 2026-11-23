<?php

namespace App\Services;

use App\Models\Payment;
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
        // A charge paid this many days after its due date or fewer still counts as on time.
        'grace_days' => 5,
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
     * Evaluate payment history without saving flags or sending SMS.
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
     * Step 2 of a risk check: the current-overdue rule and history metrics as of $asOf. Only charges due by then count, and a
     * payment made after $asOf still counts as unpaid on that day. A month is late when any charge
     * due in it was paid (or is still unpaid) more than grace_days after its due date. Late months,
     * the late streak and the average use the last 6 months; "recent default" counts days since the
     * last late charge was settled, so a charge that is still overdue counts as 0 days ago.
     */
    public function evaluatePrepared(array $charges, Carbon $asOf, array $thresholds): array
    {
        $today = self::dayNumber($asOf);
        $sixMonthsAgo = self::dayNumber($asOf->copy()->startOfDay()->subMonthsNoOverflow(6));
        $grace = (int) $thresholds['grace_days'];
        $months = [];          // month => worst days late of its charges (6-month window only)
        $undecided = [];       // month => true while a charge is unpaid but still within the grace period
        $lastLateEnd = null;   // day the most recent late charge was settled (today if still unpaid)
        $overdueNow = false;

        foreach ($charges as $charge) {
            if ($charge['due'] > $today) {
                continue;
            }
            $paidByThen = $charge['recorded'] && ($charge['paid'] === null || $charge['paid'] <= $today);
            $daysLate = $paidByThen
                ? max(0, $charge['paid'] !== null ? $charge['paid'] - $charge['due'] : $charge['stored'])
                : max(0, $today - $charge['due']);
            $late = $daysLate > $grace;
            if (! $paidByThen && $charge['due'] < $today) {
                $overdueNow = true;
            }

            // Recent default looks at all history: an old charge that is still unpaid is a default today.
            if ($late) {
                $end = $paidByThen ? ($charge['paid'] ?? $charge['due'] + $charge['stored']) : $today;
                $lastLateEnd = max($lastLateEnd ?? $end, $end);
            }
            if ($charge['due'] < $sixMonthsAgo) {
                continue;
            }
            // Rent, utility shares and part-payments due in one month count as one month, judged by the worst.
            $months[$charge['month']] = max($months[$charge['month']] ?? 0, $daysLate);
            if (! $paidByThen && ! $late) {
                $undecided[$charge['month']] = true;
            }
        }

        ksort($months);
        $lateMonths = array_keys(array_filter($months, fn (int $days) => $days > $grace));

        // Current streak: late months in a row up to the latest month whose outcome is known. A month
        // still inside its grace period is skipped rather than counted as on time.
        $streak = 0;
        $expected = null;
        foreach (array_reverse(array_keys($months)) as $month) {
            $isLate = $months[$month] > $grace;
            if (! $isLate && isset($undecided[$month]) && $expected === null) {
                continue;
            }
            if (! $isLate || ($expected !== null && $month !== $expected)) {
                break;
            }
            $streak++;
            $expected = $month - 1;
        }

        $metrics = [
            'late_payments_6mo' => count($lateMonths),
            'consecutive_late_months' => $streak,
            'avg_days_overdue' => round($months === [] ? 0.0 : array_sum($months) / count($months), 1),
            'days_since_last_default' => $lastLateEnd === null ? null : max(0, $today - $lastLateEnd),
        ];
        $rules = [
            'Overdue now' => $overdueNow,
            'Repeated late payments' => $metrics['late_payments_6mo'] >= (int) $thresholds['late_payments'],
            'Consecutive late months' => $metrics['consecutive_late_months'] >= (int) $thresholds['consecutive_months'],
            'Average days overdue' => $metrics['avg_days_overdue'] > (float) $thresholds['average_days'],
            'Recent default' => $metrics['days_since_last_default'] !== null && $metrics['days_since_last_default'] <= (int) $thresholds['recent_default_days'],
        ];
        $count = count(array_filter($rules));

        // High requires all three reminder warnings: overdue now, a consecutive late streak, and
        // repeated late months. Other history warnings can still produce Medium.
        $highRisk = $rules['Overdue now']
            && $rules['Consecutive late months']
            && $rules['Repeated late payments'];

        return [...$metrics, 'rules' => $rules, 'rules_tripped' => $count, 'risk_level' => $highRisk ? 'high' : ($count > 0 ? 'medium' : 'low')];
    }

    /** Whole days since 1970 in the date's own (Philippine) time zone, so date differences are plain subtraction. */
    private static function dayNumber(Carbon $date): int
    {
        return intdiv($date->getTimestamp() + $date->getOffset(), 86400);
    }

    /** Reason shown when a charge is still unpaid past its due date. */
    public const UNPAID_NOW = 'Has an overdue balance right now';

    /** Why a tenant is flagged, in everyday words (one per rule that tripped), for the dashboard and tenant page. */
    public function reasons(array $evaluation): array
    {
        $plural = fn (int $n, string $word) => $n.' '.Str::plural($word, $n);
        $days = $evaluation['days_since_last_default'];
        $avg = (int) round($evaluation['avg_days_overdue']);

        return array_values(array_filter([
            $evaluation['rules']['Overdue now'] ? self::UNPAID_NOW : null,
            $evaluation['rules']['Repeated late payments'] ? 'Late '.$plural($evaluation['late_payments_6mo'], 'time').' in the last 6 months' : null,
            $evaluation['rules']['Consecutive late months'] ? 'Late '.$evaluation['consecutive_late_months'].' months in a row' : null,
            $evaluation['rules']['Average days overdue'] ? 'Usually pays about '.$plural($avg, 'day').' late' : null,
            $evaluation['rules']['Recent default'] ? ($days === 0 ? null : 'Paid late just '.$plural($days, 'day').' ago') : null,
        ]));
    }
}
