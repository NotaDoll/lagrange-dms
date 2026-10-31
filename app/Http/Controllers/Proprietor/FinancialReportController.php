<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        $dateFrom = $filters['date_from'] ?? now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();
        // Validate resolved defaults too (for requests supplying only one endpoint).
        validator(['date_from' => $dateFrom, 'date_to' => $dateTo], [
            'date_to' => ['after_or_equal:date_from'],
        ])->validate();
        $tenantId = $filters['tenant_id'] ?? null;

        $reportTenants = Tenant::with(['user', 'currentAssignment.bed.room', 'roomAssignments.bed.room', 'payments'])
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))->get()->keyBy('id');
        $payments = $reportTenants->flatMap(fn ($tenant) => app(TenantBalanceService::class)
            ->billingEntries($tenant, $dateFrom, $dateTo))->values();
        $paid = $payments->where('status', 'paid');
        $outstanding = $payments->whereIn('status', ['pending', 'overdue']);
        $overdue = $outstanding->filter(fn ($payment) => $payment->due_date->lt(today()));
        $totalBilled = (float) $payments->sum('amount');
        $totalCollected = (float) $paid->sum('amount');
        $totalOutstanding = (float) $outstanding->sum('amount');
        $totalOverdue = (float) $overdue->sum('amount');
        $paidOnTime = $paid->filter(fn ($payment) => $payment->paid_date && $payment->paid_date->lte($payment->due_date))->count();
        $paidLate = $paid->filter(fn ($payment) => $payment->paid_date && $payment->paid_date->gt($payment->due_date))->count();
        $unknownPaidDate = $paid->whereNull('paid_date')->count();

        $tenants = Tenant::with('user')->join('users', 'users.id', '=', 'tenants.user_id')
            ->orderBy('users.name')->select('tenants.*')->get();
        $tenantRows = $payments->groupBy('tenant_id')->map(function ($entries, $id) use ($reportTenants) {
            $tenant = $reportTenants->get($id);
            $bed = $tenant?->currentAssignment?->bed;

            return (object) [
                'name' => $tenant?->user?->name ?? 'Unknown tenant',
                'bed' => $bed ? ($bed->room?->room_number.' / '.$bed->bed_label) : 'Unassigned',
                'billed' => (float) $entries->sum('amount'),
                'paid' => (float) $entries->where('status', 'paid')->sum('amount'),
                'balance' => (float) $entries->whereIn('status', ['pending', 'overdue'])->sum('amount'),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $balanceRows = Tenant::with(['user', 'currentAssignment.bed.room', 'roomAssignments.bed.room', 'payments'])
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))->get()
            ->map(function ($tenant) {
                $summary = app(TenantBalanceService::class)->outstandingSummary($tenant);
                $bed = $tenant->currentAssignment?->bed;

                return (object) [
                    'name' => $tenant->user?->name ?? 'Unknown tenant',
                    'bed' => $bed ? ($bed->room?->room_number.' / '.$bed->bed_label) : 'Unassigned',
                    'balance' => $summary['outstanding_balance'],
                    'unpaid_months' => $summary['unpaid_months'],
                ];
            })->filter(fn ($row) => $row->balance > 0)->sortByDesc('balance')->values();
        $currentOutstanding = (float) $balanceRows->sum('balance');
        $unpaidTenantCount = $balanceRows->count();

        $monthly = $payments->groupBy(fn ($payment) => $payment->due_date->format('Y-m'));
        $months = collect();
        for ($month = Carbon::parse($dateFrom)->startOfMonth(); $month->lte(Carbon::parse($dateTo)->startOfMonth()); $month->addMonth()) {
            $months->push((object) [
                'label' => $month->format('M Y'),
                'amount' => (float) $monthly->get($month->format('Y-m'), collect())->where('status', 'paid')->sum('amount'),
            ]);
        }
        $chartMax = max(1, $months->max('amount'));
        $selectedTenant = $tenantId ? $tenants->firstWhere('id', $tenantId)?->user?->name : 'All tenants';
        $rangeLabel = Carbon::parse($dateFrom)->format('M j, Y').' – '.Carbon::parse($dateTo)->format('M j, Y');

        return view('proprietor.reports.index', compact(
            'balanceRows', 'currentOutstanding', 'unpaidTenantCount',
            'dateFrom', 'dateTo', 'tenantId', 'tenants', 'selectedTenant', 'rangeLabel',
            'totalBilled', 'totalCollected', 'totalOutstanding', 'totalOverdue',
            'paid', 'paidOnTime', 'paidLate', 'unknownPaidDate', 'overdue', 'tenantRows', 'months', 'chartMax'
        ));
    }
}
