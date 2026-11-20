<?php

namespace App\Services;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TenantMonthMode;
use Illuminate\Support\Facades\Cache;
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
        $todayDate = Carbon::parse(Tenant::localDate());
        $monthStart = $todayDate->copy()->startOfMonth();
        $monthEnd = $todayDate->copy()->endOfMonth()->startOfDay();

        // Each tenant's ledger is built ONCE (it's the slowest step) and every figure below is filtered
        // from it: collections, overdue, amount owed, and risk. It runs to the end of this month, or to
        // the latest utility share if that's later, so "owed" matches TenantBalanceService::outstandingSummary().
        $ledgers = $tenants->mapWithKeys(function (Tenant $tenant) use ($monthEnd) {
            $latestUtility = $tenant->payments->whereNotNull('utility_bill_id')->max('due_date');
            $end = $latestUtility && $latestUtility->gt($monthEnd) ? $latestUtility : $monthEnd;

            return [$tenant->id => $this->balances->billingEntries($tenant, $this->balances->ledgerStart($tenant), $end->toDateString())];
        });
        $all = $ledgers->flatten(1)->filter(fn ($entry) => $entry->due_date->lte($monthEnd))->values();
        $period = $all->filter(fn ($entry) => $entry->due_date->gte($monthStart))->values();
        $overdue = $all->filter(fn ($entry) => $entry->status !== 'paid' && $entry->due_date->lt(today()));
        $owes = fn (Tenant $tenant) => round((float) $ledgers[$tenant->id]
            ->filter(fn ($entry) => $entry->utility_bill_id || $entry->due_date->lte($todayDate))
            ->whereIn('status', ['pending', 'overdue'])->sum('amount'), 2);

        $thresholds = $this->risk->thresholds();
        $active = $tenants->where('status', 'active');
        $history = $this->cachedRiskHistory($active, $thresholds, max(6, $trendMonths), $ledgers);
        $evaluated = $active->map(function ($tenant) use ($thresholds, $history, $ledgers, $owes) {
            $evaluation = $this->risk->evaluate($tenant, $thresholds, null, $ledgers[$tenant->id]);
            // Sparkline: the last 6 months of the same history the trend chart uses.
            $trend = collect($this->trendMonthStarts(6))->map(fn (Carbon $month) => [
                'label' => $month->format('M'), 'level' => $history[$tenant->id][$month->format('Y-m')] ?? null,
            ]);

            return (object) ['tenant' => $tenant, ...$evaluation, 'trend' => $trend,
                'reasons' => $this->risk->reasons($evaluation),
                'owes' => $owes($tenant), 'sms_sent' => $tenant->smsLogs->isNotEmpty(), 'sms_status' => $tenant->smsLogs->first()?->status, 'sms_log_id' => $tenant->smsLogs->first()?->id];
        });
        $riskCounts = array_merge(['high' => 0, 'medium' => 0, 'low' => 0], $evaluated->countBy('risk_level')->all());
        $followUps = $evaluated->filter(fn ($row) => $row->risk_level !== 'low')
            ->sort(fn ($left, $right) => $right->rules_tripped <=> $left->rules_tripped ?: $right->owes <=> $left->owes ?: strcasecmp($left->tenant->name, $right->tenant->name))->values();
        $totalBeds = Bed::count();
        // These are the exact occupancy/availability predicates used by RoomController.
        $occupiedBeds = RoomAssignment::where('status', 'active')->count();
        $availableBeds = Bed::where('status', 'available')->whereDoesntHave('currentAssignment')->count();
        $complaints = Complaint::select('id', 'status', 'category', 'priority', 'priority_reason', 'created_at')->get();
        $open = $complaints->whereIn('status', ['submitted', 'in_progress']);
        // Needs attention now: flagged urgent by the AI's one-time check, or unresolved for more than 7 days.
        $urgent = $open->where('priority', ComplaintUrgencyService::URGENT)->sortBy('created_at')->values();
        $waiting = $open->filter(fn ($complaint) => $complaint->created_at->lt(now()->subDays(7)));
        $latestSummary = AiSummary::latest('id')->first();

        return [
            'totalBeds' => $totalBeds, 'occupiedBeds' => $occupiedBeds, 'availableBeds' => $availableBeds,
            'occupancyRate' => $totalBeds ? round($occupiedBeds / $totalBeds * 100, 1) : 0,
            'expectedThisMonth' => (float) $period->sum('amount'),
            'collectedThisMonth' => (float) $period->where('status', 'paid')->sum('amount'),
            'overdueBalance' => (float) $overdue->sum('amount'), 'overdueTenants' => $overdue->pluck('tenant_id')->unique()->count(),
            'openComplaints' => $open->count(),
            'newComplaintsToday' => $open->filter(fn ($complaint) => $complaint->created_at->isToday())->count(),
            'oldComplaints' => $waiting->count(),
            'urgentComplaints' => $urgent,
            'attentionComplaintIds' => $urgent->concat($waiting)->pluck('id')->unique()->values()->all(),
            'reservedRooms' => Room::where('status', 'reserved')->count(),
            'upcomingMoveIns' => RoomAssignment::where('status', 'active')->whereDate('start_date', '>', today())->count(),
            'nextMoveIn' => RoomAssignment::where('status', 'active')->whereDate('start_date', '>', today())->min('start_date'),
            'followUps' => $followUps, 'highRiskCount' => $followUps->where('risk_level', 'high')->count(),
            'latestSummary' => $latestSummary,
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
        // Done once per tenant, then reused for every month (this is what keeps the history fast).
        $starts = $tenants->mapWithKeys(fn (Tenant $tenant) => [$tenant->id => $this->policy->tenantStart($tenant)]);
        $charges = $tenants->mapWithKeys(fn (Tenant $tenant) => [$tenant->id => $this->risk->prepareCharges($entries[$tenant->id])]);
        $firstDue = $tenants->mapWithKeys(fn (Tenant $tenant) => [$tenant->id => $tenant->payments->min('due_date')]);

        foreach ($this->trendMonthStarts($months) as $month) {
            $asOf = $month->isSameMonth($today) ? $today->copy() : $month->copy()->endOfMonth()->startOfDay();
            foreach ($tenants as $tenant) {
                $start = $starts[$tenant->id];
                $here = $start !== null && $start->lte($asOf) && ($this->policy->assignmentOverlaps($tenant, $month, $asOf)
                    || ($firstDue[$tenant->id] !== null && $firstDue[$tenant->id]->lte($asOf)));
                $history[$tenant->id][$month->format('Y-m')] = $here
                    ? $this->risk->evaluatePrepared($charges[$tenant->id], $asOf, $thresholds)['risk_level']
                    : null;
            }
        }

        return $history;
    }

    /**
     * riskHistory() is the costliest part of the dashboard (tenants × months risk checks), and past
     * months don't change unless the data does. So the result is kept for the day and thrown away as
     * soon as anything it depends on changes: payments, tenants, room assignments, storage months,
     * settings (billing start, thresholds) or the date itself.
     */
    private function cachedRiskHistory(Collection $tenants, array $thresholds, int $months, Collection $entries): array
    {
        $version = md5(json_encode([
            Tenant::localDate(), $thresholds, $months, $tenants->pluck('id')->values(),
            Payment::count(), Payment::max('updated_at'),
            Tenant::count(), Tenant::max('updated_at'),
            RoomAssignment::count(), RoomAssignment::max('updated_at'),
            TenantMonthMode::count(), TenantMonthMode::max('updated_at'),
            Setting::max('updated_at'),
        ]));

        return Cache::remember('dashboard.risk-history.'.$version, now()->addDay(),
            fn () => $this->riskHistory($tenants, $thresholds, $months, $entries));
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
            return ['themes' => [], 'categories' => [], 'recategorized' => [], 'action' => null, 'text' => null, 'alert' => null, 'total' => null];
        }
        $parsed = json_decode($summary->summary_result, true);
        $total = $summary->filter_params['complaint_count'] ?? null;
        $themes = is_array($parsed) ? collect($parsed['themes'] ?? [])->filter(fn ($theme) => is_array($theme) && is_string($theme['theme'] ?? null) && is_numeric($theme['count'] ?? null))->values()->all() : [];
        // Summaries made before AI categorization have no categories: show their issues under one heading.
        $categories = is_array($parsed['categories'] ?? null) ? $parsed['categories']
            : ($themes ? [['category' => null, 'label' => 'Issues', 'count' => array_sum(array_column($themes, 'count')), 'issues' => $themes]] : []);
        $text = $themes ? null : strip_tags($summary->summary_result);
        $first = $themes[0] ?? null;

        return [
            'themes' => $themes, 'total' => $total, 'categories' => $categories,
            'recategorized' => is_array($parsed['recategorized'] ?? null) ? $parsed['recategorized'] : [],
            'action' => is_array($parsed) && is_string($parsed['recommended_action'] ?? null) ? $parsed['recommended_action'] : null,
            'text' => $text,
            'alert' => $first ? $first['theme'].' — '.$first['count'].($total !== null ? ' of '.$total : '').' complaints in the saved summary.' : Str::limit($text, 190),
        ];
    }
}
