<?php

namespace App\Services;

use App\Models\RiskFlag;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class PaymentRiskService
{
    public const DEFAULT_THRESHOLDS = [
        'late_payments' => 2,
        'consecutive_months' => 2,
        'average_days' => 5,
        'recent_default_days' => 30,
    ];

    public function __construct(private readonly SmsService $smsService, private readonly SettingsService $settings) {}

    public function thresholds(): array
    {
        return array_replace(self::DEFAULT_THRESHOLDS, array_intersect_key($this->settings->getGroup('risk'), self::DEFAULT_THRESHOLDS));
    }

    /** Evaluate the existing four rules without saving flags or sending SMS. */
    public function evaluate(Tenant $tenant, ?array $thresholds = null): array
    {
        $tenant->loadMissing('payments');
        $payments = $tenant->payments;
        $late = $payments->filter(fn ($payment) => $payment->status === 'overdue' || $payment->days_overdue > 0);
        $months = $late->map(fn ($payment) => $payment->due_date->format('Y-m'))->unique()->sort()->values();
        $streak = 0;
        $consecutive = 0;
        $previous = null;
        foreach ($months as $month) {
            $current = Carbon::parse($month.'-01');
            $streak = $previous && $previous->copy()->addMonth()->isSameMonth($current) ? $streak + 1 : 1;
            $consecutive = max($consecutive, $streak);
            $previous = $current;
        }
        $last = $late->sortByDesc('due_date')->first()?->due_date;
        $metrics = [
            'late_payments_6mo' => $late->filter(fn ($payment) => $payment->due_date->gte(now()->subMonths(6)->startOfDay()))->count(),
            'consecutive_late_months' => $consecutive,
            'avg_days_overdue' => round((float) $payments->avg('days_overdue'), 1),
            'days_since_last_default' => $last ? (int) $last->copy()->startOfDay()->diffInDays(today()) : null,
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

    public function calculateForTenant(Tenant $tenant): RiskFlag
    {
        $evaluation = $this->evaluate($tenant);
        $flag = RiskFlag::create([
            'tenant_id' => $tenant->id,
            ...array_diff_key($evaluation, array_flip(['rules', 'rules_tripped'])),
            'calculated_at' => now(),
        ]);
        if ($flag->risk_level === 'high' && $tenant->user?->contact_number) {
            $this->smsService->send($tenant->user->contact_number, 'Your dormitory account has been flagged high risk due to repeated late payments. Please settle outstanding balances as soon as possible.');
        }

        // No guardian delivery: this database has no records of both required consents.
        return $flag;
    }

    public function calculateForAllTenants(): int
    {
        return Tenant::where('status', 'active')->with(['user', 'payments'])->get()
            ->each(fn ($tenant) => $this->calculateForTenant($tenant))->count();
    }
}
