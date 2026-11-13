<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RiskFlag;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class PaymentRiskService
{
    public const DEFAULT_THRESHOLDS = [
        'late_payments' => 2,
        'consecutive_months' => 2,
        'average_days' => 5,
        'recent_default_days' => 30,
    ];

    public function __construct(
        private readonly SmsService $smsService,
        private readonly SettingsService $settings,
        private readonly TenantBalanceService $balances,
    ) {}

    public function thresholds(): array
    {
        return array_replace(self::DEFAULT_THRESHOLDS, array_intersect_key($this->settings->getGroup('risk'), self::DEFAULT_THRESHOLDS));
    }

    /** Evaluate the existing four rules without saving flags or sending SMS. */
    public function evaluate(Tenant $tenant, ?array $thresholds = null): array
    {
        $today = Carbon::parse(Tenant::localDate());
        $charges = $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $today->toDateString())
            ->where('category', '!=', Payment::OPENING_BALANCE)
            ->map(fn (Payment $payment) => [
                'due_date' => $payment->due_date,
                'days_overdue' => $payment->hasRecordedPayment()
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

    public function calculateForTenant(Tenant $tenant): RiskFlag
    {
        $evaluation = $this->evaluate($tenant);
        $flag = RiskFlag::create([
            'tenant_id' => $tenant->id,
            ...array_diff_key($evaluation, array_flip(['rules', 'rules_tripped'])),
            'calculated_at' => now(),
        ]);
        if ($flag->risk_level === 'high' && filled($tenant->guardian_contact_number)) {
            Cache::lock('dashboard.sms.'.$tenant->id, 30)->get(function () use ($tenant) {
                if (! $tenant->smsLogs()->paymentFollowUpToday()->exists()) {
                    $this->smsService->sendGuardianPaymentFollowUp($tenant);
                }
            });
        }

        return $flag;
    }

    public function calculateForAllTenants(): int
    {
        return Tenant::where('status', 'active')->with(['user', 'payments', 'monthModes', 'roomAssignments.bed.room'])->get()
            ->each(fn ($tenant) => $this->calculateForTenant($tenant))->count();
    }
}
