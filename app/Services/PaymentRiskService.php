<?php

namespace App\Services;

use App\Models\RiskFlag;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class PaymentRiskService
{
    public function __construct(private readonly SmsService $smsService)
    {
    }

    private const LATE_PAYMENTS_WINDOW_MONTHS = 6;
    private const LATE_PAYMENTS_THRESHOLD = 2;
    private const CONSECUTIVE_LATE_MONTHS_THRESHOLD = 2;
    private const AVG_DAYS_OVERDUE_THRESHOLD = 5;
    private const RECENT_DEFAULT_DAYS_THRESHOLD = 30;

    public function calculateForTenant(Tenant $tenant): RiskFlag
    {
        $now = now();
        $payments = $tenant->payments()
            ->orderBy('due_date')
            ->get();

        $latePayments = $payments->filter(fn ($payment) => $this->isLatePayment($payment));

        $latePayments6Mo = $latePayments
            ->filter(fn ($payment) => $payment->due_date?->gte($now->copy()->subMonths(self::LATE_PAYMENTS_WINDOW_MONTHS)->startOfDay()))
            ->count();

        $consecutiveLateMonths = $this->maxConsecutiveLateMonths($latePayments);
        $avgDaysOverdue = round((float) $payments->avg('days_overdue'), 1);
        $lastDefaultDate = $latePayments
            ->sortByDesc('due_date')
            ->first()
            ?->due_date;
        $daysSinceLastDefault = $lastDefaultDate
            ? (int) $lastDefaultDate->startOfDay()->diffInDays($now->copy()->startOfDay())
            : null;

        $triggeredIndicators = collect([
            $latePayments6Mo >= self::LATE_PAYMENTS_THRESHOLD,
            $consecutiveLateMonths >= self::CONSECUTIVE_LATE_MONTHS_THRESHOLD,
            $avgDaysOverdue > self::AVG_DAYS_OVERDUE_THRESHOLD,
            $daysSinceLastDefault !== null && $daysSinceLastDefault <= self::RECENT_DEFAULT_DAYS_THRESHOLD,
        ])->filter()->count();

        $riskLevel = match (true) {
            $triggeredIndicators >= 2 => 'high',
            $triggeredIndicators === 1 => 'medium',
            default => 'low',
        };

        $riskFlag = RiskFlag::create([
            'tenant_id' => $tenant->id,
            'risk_level' => $riskLevel,
            'late_payments_6mo' => $latePayments6Mo,
            'consecutive_late_months' => $consecutiveLateMonths,
            'avg_days_overdue' => $avgDaysOverdue,
            'days_since_last_default' => $daysSinceLastDefault,
            'calculated_at' => $now,
        ]);

        if ($riskLevel === 'high') {
            $this->notifyHighRiskTenant($tenant);
        }

        return $riskFlag;
    }

    public function calculateForAllTenants(): int
    {
        return Tenant::query()
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->each(fn (Tenant $tenant) => $this->calculateForTenant($tenant))
            ->count();
    }

    private function isLatePayment($payment): bool
    {
        return $payment->status === 'overdue' || $payment->days_overdue > 0;
    }

    private function maxConsecutiveLateMonths($latePayments): int
    {
        $lateMonths = $latePayments
            ->filter(fn ($payment) => $payment->due_date !== null)
            ->map(fn ($payment) => $payment->due_date->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        if ($lateMonths->isEmpty()) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;
        $previousMonth = Carbon::createFromFormat('Y-m', $lateMonths->first())->startOfMonth();

        foreach ($lateMonths->skip(1) as $month) {
            $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            if ($previousMonth->copy()->addMonth()->isSameMonth($currentMonth)) {
                $currentStreak++;
            } else {
                $currentStreak = 1;
            }

            $maxStreak = max($maxStreak, $currentStreak);
            $previousMonth = $currentMonth;
        }

        return $maxStreak;
    }

    private function notifyHighRiskTenant(Tenant $tenant): void
    {
        $message = 'Your dormitory account has been flagged high risk due to repeated late payments. Please settle outstanding balances as soon as possible.';

        if ($tenant->user?->contact_number) {
            $this->smsService->send($tenant->user->contact_number, $message);
        }

        if ($tenant->guardian_contact_number) {
            $this->smsService->send($tenant->guardian_contact_number, $message);
        }
    }
}
