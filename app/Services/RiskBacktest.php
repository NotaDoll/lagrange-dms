<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Checks how well the payment risk flags predicted what tenants actually did (manuscript 3.6.2).
 *
 * For each cutoff (the end of a past month) every tenant living there is flagged using only what was
 * known on that day (PaymentRiskService::evaluate with $asOf). The flag is then compared with the
 * next $windowMonths: the tenant "defaulted" if any charge due in that window was paid more than
 * $graceDays late, or was still unpaid when the window ended. Windows that end after today are
 * skipped, because their outcome isn't known yet.
 */
class RiskBacktest
{
    public function __construct(
        private readonly PaymentRiskService $risk,
        private readonly TenantBalanceService $balances,
        private readonly BillingPolicy $policy,
    ) {}

    /**
     * @param  string  $flagAt  'high' counts only High as "flagged"; 'medium' counts Medium or High.
     * @return array{observations: list<array>, cutoffs: list<array>, totals: array}
     */
    public function run(int $months = 6, int $windowMonths = 2, ?int $graceDays = null, string $flagAt = 'high'): array
    {
        $today = Carbon::parse(Tenant::localDate());
        $thresholds = $this->risk->thresholds();
        // By default "defaulted" uses the same grace period the risk rules use to call a month late.
        $graceDays ??= (int) $thresholds['grace_days'];
        $flagged = $flagAt === 'medium' ? ['medium', 'high'] : ['high'];
        $tenants = Tenant::with(['user', 'payments', 'monthModes', 'roomAssignments.bed.room'])
            ->where('status', '!=', Tenant::STATUS_RESERVED)->get();

        $observations = [];
        // Latest cutoff whose whole outcome window has already passed, then step back month by month.
        $latest = $today->copy()->subMonthsNoOverflow($windowMonths)->startOfMonth()->subDay();
        for ($i = $months - 1; $i >= 0; $i--) {
            $cutoff = $latest->copy()->startOfMonth()->subMonthsNoOverflow($i)->endOfMonth()->startOfDay();
            $windowEnd = $cutoff->copy()->addDay()->addMonthsNoOverflow($windowMonths)->subDay();

            foreach ($tenants as $tenant) {
                $start = $this->policy->tenantStart($tenant);
                if ($start === null || $start->gt($cutoff)) {
                    continue; // not billed yet at the cutoff
                }
                $charges = $this->chargesDueBetween($tenant, $cutoff->copy()->addDay(), $windowEnd);
                if ($charges->isEmpty()) {
                    continue; // nothing was due in the window (e.g. moved out), so there is no outcome to check
                }

                $level = $this->risk->evaluate($tenant, $thresholds, $cutoff)['risk_level'];
                $worst = (int) $charges->max(fn (Payment $charge) => $this->daysLate($charge, $windowEnd));
                $observations[] = [
                    'cutoff' => $cutoff->toDateString(),
                    'tenant_id' => $tenant->id,
                    'tenant' => (string) $tenant->name,
                    'risk_level' => $level,
                    'flagged' => in_array($level, $flagged, true),
                    'worst_days_late' => $worst,
                    'defaulted' => $worst > $graceDays,
                ];
            }
        }

        $cutoffs = collect($observations)->groupBy('cutoff')
            ->map(fn (Collection $rows, $cutoff) => ['cutoff' => $cutoff, ...$this->score($rows)])->values()->all();

        return ['observations' => $observations, 'cutoffs' => $cutoffs, 'totals' => $this->score(collect($observations))];
    }

    /** Rent, storage and utility charges due in the window, as recorded or as billed. */
    private function chargesDueBetween(Tenant $tenant, Carbon $from, Carbon $to): Collection
    {
        return $this->balances->billingEntries($tenant, $from->toDateString(), $to->toDateString())
            ->filter(fn (Payment $entry) => ! $entry->is_advance_credit && $entry->due_date->betweenIncluded($from, $to));
    }

    /** How late the charge was paid, or how overdue it still was when the window ended. */
    private function daysLate(Payment $charge, Carbon $windowEnd): int
    {
        if (! $charge->hasRecordedPayment()) {
            $paidBy = $windowEnd; // still unpaid today, so it was unpaid when the window ended
        } elseif ($charge->paid_date === null) {
            return (int) $charge->days_overdue; // paid, date not recorded (older imports): use what was saved
        } else {
            $paidBy = $charge->paid_date->min($windowEnd); // paid after the window ended = still unpaid at its end
        }

        return (int) max(0, $charge->due_date->diffInDays($paidBy, false));
    }

    /** Confusion matrix and the rates reported in Table 3.8. */
    private function score(Collection $rows): array
    {
        $tp = $rows->where('flagged', true)->where('defaulted', true)->count();
        $fp = $rows->where('flagged', true)->where('defaulted', false)->count();
        $fn = $rows->where('flagged', false)->where('defaulted', true)->count();
        $tn = $rows->where('flagged', false)->where('defaulted', false)->count();
        $total = $tp + $fp + $fn + $tn;
        $rate = fn (int $part, int $whole) => $whole === 0 ? null : round($part / $whole * 100, 1);

        return [
            'tenants_checked' => $total, 'tp' => $tp, 'fp' => $fp, 'fn' => $fn, 'tn' => $tn,
            'accuracy' => $rate($tp + $tn, $total),
            // Textbook false positive rate: of the tenants who paid on time, how many were flagged.
            'false_positive_rate' => $rate($fp, $fp + $tn),
            // Manuscript wording ("% flagged but paid on time"): of the flagged tenants, how many paid on time.
            'flagged_but_paid_on_time' => $rate($fp, $tp + $fp),
            'precision' => $rate($tp, $tp + $fp),
            'recall' => $rate($tp, $tp + $fn),
        ];
    }
}
