<?php

namespace App\Services;

use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class ProprietorDashboardService
{
    public function __construct(private readonly PaymentRiskService $risk, private readonly TenantBalanceService $balances) {}

    public function data(User $user): array
    {
        $tenants = Tenant::with([
            'user', 'currentAssignment.bed.room', 'roomAssignments.bed.room', 'payments',
            'riskFlags' => fn ($query) => $query->where('calculated_at', '>=', now()->subMonthsNoOverflow(5)->startOfMonth())->orderBy('calculated_at'),
            'smsLogs' => fn ($query) => $query->where('status', 'sent')->where('recipient_type', 'tenant')->whereDate('sent_at', today())->where(fn ($query) => $query->where('message', 'like', 'Payment follow-up:%')->orWhere('message', 'like', 'Your dormitory account has been flagged high risk%')),
        ])->get();
        $from = today()->startOfMonth()->toDateString();
        $to = today()->endOfMonth()->toDateString();
        $period = $tenants->flatMap(fn ($tenant) => $this->balances->billingEntries($tenant, $from, $to));
        $all = $tenants->flatMap(fn ($tenant) => $this->balances->billingEntries($tenant,
            min('2026-08-01', $tenant->payments->min('due_date')?->toDateString() ?? '2026-08-01'), $to));
        $overdue = $all->filter(fn ($entry) => $entry->status !== 'paid' && $entry->due_date->lt(today()));
        $thresholds = $this->risk->thresholds();
        $followUps = $tenants->where('status', 'active')->map(function ($tenant) use ($thresholds) {
            $evaluation = $this->risk->evaluate($tenant, $thresholds);
            $trend = collect();
            for ($index = 5; $index >= 0; $index--) {
                $month = today()->startOfMonth()->subMonths($index);
                $flag = $tenant->riskFlags->filter(fn ($flag) => $flag->calculated_at?->format('Y-m') === $month->format('Y-m'))->last();
                $trend->push(['label' => $month->format('M'), 'level' => $flag?->risk_level]);
            }

            return (object) ['tenant' => $tenant, ...$evaluation, 'trend' => $trend, 'sms_sent' => $tenant->smsLogs->isNotEmpty()];
        })->filter(fn ($row) => $row->risk_level !== 'low')
            ->sort(fn ($left, $right) => $right->rules_tripped <=> $left->rules_tripped ?: strcasecmp($left->tenant->user->name, $right->tenant->user->name))->values();
        $totalBeds = Bed::count();
        // These are the exact occupancy/availability predicates used by RoomController.
        $occupiedBeds = RoomAssignment::where('status', 'active')->count();
        $availableBeds = Bed::where('status', 'available')->whereDoesntHave('currentAssignment')->count();
        $floorBeds = Bed::with('room')->withExists('currentAssignment')->get();
        $floors = $floorBeds->groupBy(fn ($bed) => $bed->room->floor)->map(fn ($beds, $floor) => (object) [
            'label' => $floor, 'total' => $beds->count(), 'occupied' => $beds->where('current_assignment_exists', true)->count(),
        ])->sortKeys(SORT_NATURAL)->values();
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
            'floors' => $floors, 'latestSummary' => $latestSummary,
            'summaryContent' => $this->presentSummary($latestSummary),
            'unreadCount' => $user->notifications()->where('is_read', false)->count(),
            'periodFrom' => $from, 'periodTo' => $to,
        ];
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
