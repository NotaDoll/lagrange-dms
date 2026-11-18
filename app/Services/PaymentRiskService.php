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
        $charges = ($entries ?? $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $today->toDateString()))
            ->where('category', '!=', Payment::OPENING_BALANCE)
            ->reject(fn (Payment $payment) => $payment->is_advance_credit)
            ->filter(fn (Payment $payment) => $payment->due_date->lte($today))
            ->map(fn (Payment $payment) => [
                'due_date' => $payment->due_date,
                'days_overdue' => $payment->hasRecordedPayment() && (! $payment->paid_date || $payment->paid_date->lte($today))
                    ? (int) max(0, $payment->paid_date
                        ? $payment->due_date->diffInDays($payment->paid_date, false)
                        : $payment->days_overdue)
                    : (int) max(0, $payment->due_date->diffInDays($today, false)),
            ]);
        $late = $charges->where('days_overdue', '>', 0);
        $months = $late->map(fn ($charge) => $charge['due_date']->format('Y-m'))->unique()->sort()->values();
        $streak = 0;
        $consecutive = 0;
        $previous = null;
        foreach ($months as $month) {
            $current = Carbon::parse($month.'-01');
            $streak = $previous && $previous->copy()->addMonth()->isSameMonth($current) ? $streak + 1 : 1;
            $consecutive = max($consecutive, $streak);
            $previous = $current;
        }
        $last = $late->max('due_date');
        $sixMonthsAgo = $today->copy()->subMonthsNoOverflow(6);
        $metrics = [
            'late_payments_6mo' => $late->filter(fn ($charge) => $charge['due_date']->gte($sixMonthsAgo))->count(),
            'consecutive_late_months' => $consecutive,
            'avg_days_overdue' => round((float) $charges->avg('days_overdue'), 1),
            'days_since_last_default' => $last ? (int) $last->copy()->startOfDay()->diffInDays($today) : null,
        ];
        $thresholds ??= $this->thresholds();
        $rules = [
            'Repeated late payments' => $metrics['late_payments_6mo'] >= (int) $thresholds['late_payments'],
            'Consecutive late months' => $metrics['consecutive_late_months'] >= (int) $thresholds['consecutive_months'],
            'Average days overdue' => $metrics['avg_days_overdue'] > (float) $thresholds['average_days'],
            'Recent default' => $metrics['days_since_last_default'] !== null && $metrics['days_since_last_default'] <= (int) $thresholds['recent_default_days'],
        ];
        $count = count(array_filter($rules));

        return [...$metrics, 'rules' => $rules, 'rules_tripped' => $count, 'risk_level' => $count >= 2 ? 'high' : ($count === 1 ? 'medium' : 'low')];
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
