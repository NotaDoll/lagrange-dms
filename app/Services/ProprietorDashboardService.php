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
    private const FOLLOW_UP_RISK_ORDER = ['high' => 0, 'medium' => 1];

    public function __construct(
        private readonly PaymentRiskService $risk,
        private readonly TenantBalanceService $balances,
    ) {}

    public function data(User $user): array
    {
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
        $evaluated = $active->map(function ($tenant) use ($thresholds, $ledgers, $owes, $todayDate) {
            $evaluation = $this->risk->evaluate($tenant, $thresholds, null, $ledgers[$tenant->id]);
            // Show actual monthly payment outcomes, separate from the risk score used for SMS eligibility.
            // "Late" uses the same grace period as the risk rules, so the boxes match the flagged reason.
            $grace = (int) $thresholds['grace_days'];
            $trend = collect($this->paymentHistoryMonthStarts())->map(function (Carbon $month) use ($tenant, $ledgers, $todayDate, $grace) {
                $entries = $ledgers[$tenant->id]->filter(fn ($entry) => $entry->due_date->isSameMonth($month));
                $overdue = $entries->contains(fn ($entry) => $entry->status !== 'paid' && $entry->due_date->lt($todayDate));
                $paidLate = $entries->contains(fn ($entry) => $entry->status === 'paid'
                    && ($entry->paid_date ? (int) $entry->due_date->diffInDays($entry->paid_date, false) : (int) $entry->days_overdue) > $grace);
                $allPaid = $entries->isNotEmpty() && $entries->every(fn ($entry) => $entry->status === 'paid');
                $status = $overdue ? 'overdue' : ($paidLate ? 'late' : ($allPaid ? 'on_time' : ($entries->isNotEmpty() ? 'pending' : null)));

                return ['label' => $month->format('M'), 'status' => $status];
            });

            return (object) ['tenant' => $tenant, ...$evaluation, 'trend' => $trend,
                'reasons' => $this->risk->reasons($evaluation),
                'owes' => $owes($tenant), 'sms_sent' => $tenant->smsLogs->isNotEmpty(), 'sms_status' => $tenant->smsLogs->first()?->status, 'sms_log_id' => $tenant->smsLogs->first()?->id];
        });
        $followUps = $evaluated->filter(fn ($row) => $row->risk_level !== 'low')
            ->sort(fn ($left, $right) => self::FOLLOW_UP_RISK_ORDER[$left->risk_level] <=> self::FOLLOW_UP_RISK_ORDER[$right->risk_level]
                ?: strcasecmp($left->tenant->name, $right->tenant->name))->values();
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
            'oldestOpenComplaintDays' => $open->isEmpty() ? null : (int) $open->min('created_at')->diffInDays(now()),
            'dueSoon' => $this->dueSoon($tenants->where('status', 'active')),
            'unreadCount' => $user->notifications()->where('is_read', false)->count(),
            'periodFrom' => $from, 'periodTo' => $to,
        ];
    }

    /** @return list<\Illuminate\Support\Carbon> the last six month starts, oldest first */
    private function paymentHistoryMonthStarts(): array
    {
        $current = Carbon::parse(Tenant::localDate())->startOfMonth();

        return collect(range(5, 0))->map(fn ($back) => $current->copy()->subMonthsNoOverflow($back))->all();
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
