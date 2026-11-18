<?php

namespace App\Services;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProprietorDashboardService
{
    /** Trend chart lengths the dashboard offers, in months. */
    public const TREND_MONTHS = [6, 12];

    public function __construct(
        private readonly PaymentRiskService $risk,
        private readonly TenantBalanceService $balances,
        private readonly BillingPolicy $policy,
    ) {}

    public function data(User $user, int $trendMonths = 6): array
    {
        $trendMonths = in_array($trendMonths, self::TREND_MONTHS, true) ? $trendMonths : self::TREND_MONTHS[0];
        $tenants = Tenant::with([
            'user', 'currentAssignment.bed.room', 'roomAssignments.bed.room', 'payments', 'monthModes',
            'smsLogs' => fn ($query) => $query->paymentFollowUpToday()->latest('id'),
        ])->get();
        $from = today()->startOfMonth()->toDateString();
        $to = today()->endOfMonth()->toDateString();
        $period = $tenants->flatMap(fn ($tenant) => $this->balances->billingEntries($tenant, $from, $to));
        $all = $tenants->flatMap(fn ($tenant) => $this->balances->billingEntries($tenant,
            $this->balances->ledgerStart($tenant), $to));
        $overdue = $all->filter(fn ($entry) => $entry->status !== 'paid' && $entry->due_date->lt(today()));
        $thresholds = $this->risk->thresholds();
        $active = $tenants->where('status', 'active');
        // Each tenant's billing entries, built once and reused for every month of the risk history.
        $today = Tenant::localDate();
        $entries = $active->mapWithKeys(fn ($tenant) => [$tenant->id => $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $today)]);
        $history = $this->riskHistory($active, $thresholds, max(6, $trendMonths), $entries);
        $evaluated = $active->map(function ($tenant) use ($thresholds, $history, $entries) {
            $evaluation = $this->risk->evaluate($tenant, $thresholds, null, $entries[$tenant->id]);
            // Sparkline: the last 6 months of the same history the trend chart uses.
            $trend = collect($this->trendMonthStarts(6))->map(fn (Carbon $month) => [
                'label' => $month->format('M'), 'level' => $history[$tenant->id][$month->format('Y-m')] ?? null,
            ]);

            return (object) ['tenant' => $tenant, ...$evaluation, 'trend' => $trend,
                'reasons' => $this->risk->reasons($evaluation),
                'owes' => $this->balances->outstandingSummary($tenant)['outstanding_balance'], 'sms_sent' => $tenant->smsLogs->isNotEmpty(), 'sms_status' => $tenant->smsLogs->first()?->status, 'sms_log_id' => $tenant->smsLogs->first()?->id];
        });
        $riskCounts = array_merge(['high' => 0, 'medium' => 0, 'low' => 0], $evaluated->countBy('risk_level')->all());
        $followUps = $evaluated->filter(fn ($row) => $row->risk_level !== 'low')
            ->sort(fn ($left, $right) => $right->rules_tripped <=> $left->rules_tripped ?: $right->owes <=> $left->owes ?: strcasecmp($left->tenant->name, $right->tenant->name))->values();
        $totalBeds = Bed::count();
        // These are the exact occupancy/availability predicates used by RoomController.
        $occupiedBeds = RoomAssignment::where('status', 'active')->count();
        $availableBeds = Bed::where('status', 'available')->whereDoesntHave('currentAssignment')->count();
        $complaints = Complaint::select('id', 'status', 'category', 'created_at')->get();
        $open = $complaints->whereIn('status', ['submitted', 'in_progress']);
        $latestSummary = AiSummary::latest('id')->first();

        return [
            'totalBeds' => $totalBeds, 'occupiedBeds' => $occupiedBeds, 'availableBeds' => $availableBeds,
            'occupancyRate' => $totalBeds ? round($occupiedBeds / $totalBeds * 100, 1) : 0,
            'expectedThisMonth' => (float) $period->sum('amount'),
            'collectedThisMonth' => (float) $period->where('status', 'paid')->sum('amount'),
            'overdueBalance' => (float) $overdue->sum('amount'), 'overdueTenants' => $overdue->pluck('tenant_id')->unique()->count(),
            'openComplaints' => $open->count(), 'submittedComplaints' => $open->where('status', 'submitted')->count(),
            'inProgressComplaints' => $open->where('status', 'in_progress')->count(),
            'newComplaintsToday' => $open->filter(fn ($complaint) => $complaint->created_at->isToday())->count(),
            'oldComplaints' => $open->filter(fn ($complaint) => $complaint->created_at->lt(now()->subDays(7)))->count(),
            'reservedRooms' => Room::where('status', 'reserved')->count(),
            'upcomingMoveIns' => RoomAssignment::where('status', 'active')->whereDate('start_date', '>', today())->count(),
            'nextMoveIn' => RoomAssignment::where('status', 'active')->whereDate('start_date', '>', today())->min('start_date'),
            'followUps' => $followUps, 'highRiskCount' => $followUps->where('risk_level', 'high')->count(),
            'latestSummary' => $latestSummary,
            'complaintsByCategory' => $this->complaintsByCategory($complaints, $trendMonths),
            'summaryContent' => $this->presentSummary($latestSummary),
            'riskCounts' => $riskCounts,
            'oldestOpenComplaintDays' => $open->isEmpty() ? null : (int) $open->min('created_at')->diffInDays(now()),
            'dueSoon' => $this->dueSoon($tenants->where('status', 'active')),
            'trendMonths' => $trendMonths,
            'collectionsTrend' => $this->collectionsTrend($all, $trendMonths),
            'riskTrend' => $this->riskTrend($history, $trendMonths),
            'unreadCount' => $user->notifications()->where('is_read', false)->count(),
            'periodFrom' => $from, 'periodTo' => $to,
        ];
    }

    /** @return list<\Illuminate\Support\Carbon> first day of each month, oldest first, ending with this month */
    private function trendMonthStarts(int $months): array
    {
        $current = Carbon::parse(Tenant::localDate())->startOfMonth();

        return collect(range($months - 1, 0))->map(fn ($back) => $current->copy()->subMonthsNoOverflow($back))->all();
    }

    /**
     * Per month: everything billed that month (by due date) and how much of it is paid.
     * Uses the same ledger entries as the stat cards, so the numbers agree with them.
     */
    private function collectionsTrend(Collection $entries, int $months): array
    {
        $byMonth = $entries->groupBy(fn ($entry) => $entry->due_date->format('Y-m'));

        return collect($this->trendMonthStarts($months))->map(function (Carbon $month) use ($byMonth) {
            $rows = $byMonth->get($month->format('Y-m'), collect());

            return [
                'label' => $month->format('M Y'),
                'expected' => round((float) $rows->sum('amount'), 2),
                'collected' => round((float) $rows->where('status', 'paid')->sum('amount'), 2),
            ];
        })->all();
    }

    /**
     * Each tenant's risk level at the end of every month (today for the current month), using the
     * same four rules as the follow-up list. Worked out from payment history, so it needs no saved
     * snapshots. Null for a month the tenant wasn't billed yet or had neither a bed nor a charge.
     *
     * @return array<int, array<string, ?string>> tenant id => ['Y-m' => 'high'|'medium'|'low'|null]
     */
    private function riskHistory(Collection $tenants, array $thresholds, int $months, Collection $entries): array
    {
        $today = Carbon::parse(Tenant::localDate());
        $history = [];

        foreach ($this->trendMonthStarts($months) as $month) {
            $asOf = $month->isSameMonth($today) ? $today->copy() : $month->copy()->endOfMonth()->startOfDay();
            foreach ($tenants as $tenant) {
                $start = $this->policy->tenantStart($tenant);
                $here = $start !== null && $start->lte($asOf) && ($this->policy->assignmentOverlaps($tenant, $month, $asOf)
                    || $tenant->payments->contains(fn ($payment) => $payment->due_date->lte($asOf)));
                $history[$tenant->id][$month->format('Y-m')] = $here
                    ? $this->risk->evaluate($tenant, $thresholds, $asOf, $entries[$tenant->id])['risk_level']
                    : null;
            }
        }

        return $history;
    }

    /** Per month: how many tenants were high, medium, and low risk (from riskHistory). */
    private function riskTrend(array $history, int $months): array
    {
        return collect($this->trendMonthStarts($months))->map(function (Carbon $month) use ($history) {
            $levels = array_filter(array_column($history, $month->format('Y-m')));
            $counts = array_merge(['high' => 0, 'medium' => 0, 'low' => 0], array_count_values($levels));

            return ['label' => $month->format('M Y'), ...$counts];
        })->all();
    }

    /** Complaints filed in the trend period, per category, split into still open and resolved. */
    private function complaintsByCategory(Collection $complaints, int $months): array
    {
        $since = Carbon::parse(Tenant::localDate())->startOfMonth()->subMonthsNoOverflow($months - 1);
        $recent = $complaints->filter(fn ($complaint) => $complaint->created_at->gte($since));
        $labels = ['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'];
        foreach ($recent->pluck('category')->unique() as $category) {
            $labels[$category] ??= Str::headline((string) $category);
        }

        return collect($labels)->map(function (string $label, string $category) use ($recent) {
            $rows = $recent->where('category', $category);
            $resolved = $rows->where('status', Complaint::STATUS_RESOLVED)->count();

            return ['category' => $category, 'label' => $label, 'open' => $rows->count() - $resolved, 'resolved' => $resolved];
        })->sortByDesc(fn ($row) => $row['open'] + $row['resolved'])->values()->all();
    }

    /** Rent coming due in the next 7 days (from each tenant's move-in day) that isn't paid yet. */
    private function dueSoon(Collection $tenants): Collection
    {
        $today = Carbon::parse(Tenant::localDate());
        $until = $today->copy()->addDays(7);

        return $tenants->flatMap(function (Tenant $tenant) use ($today, $until) {
            return collect([$today->copy()->startOfMonth(), $today->copy()->startOfMonth()->addMonthNoOverflow()])
                ->map(function (Carbon $month) use ($tenant, $today, $until) {
                    $balance = $this->balances->monthBalance($tenant, $month);
                    $due = $balance['charge']?->due_date;
                    if ($due === null || $due->lt($today) || $due->gt($until) || $balance['remaining'] <= 0) {
                        return null;
                    }

                    return (object) ['tenant' => $tenant, 'due_date' => $due, 'amount' => $balance['remaining']];
                })->filter();
        })->sortBy(fn ($row) => $row->due_date->timestamp)->values();
    }

    private function presentSummary(?AiSummary $summary): array
    {
        if (! $summary) {
            return ['themes' => [], 'action' => null, 'text' => null, 'alert' => null, 'total' => null];
        }
        $parsed = json_decode($summary->summary_result, true);
        $total = $summary->filter_params['complaint_count'] ?? null;
        $themes = is_array($parsed) ? collect($parsed['themes'] ?? [])->filter(fn ($theme) => is_array($theme) && is_string($theme['theme'] ?? null) && is_numeric($theme['count'] ?? null))->values()->all() : [];
        $text = $themes ? null : strip_tags($summary->summary_result);
        $first = $themes[0] ?? null;

        return [
            'themes' => $themes, 'total' => $total,
            'action' => is_array($parsed) && is_string($parsed['recommended_action'] ?? null) ? $parsed['recommended_action'] : null,
            'text' => $text,
            'alert' => $first ? $first['theme'].' — '.$first['count'].($total !== null ? ' of '.$total : '').' complaints in the saved summary.' : Str::limit($text, 190),
        ];
    }
}
