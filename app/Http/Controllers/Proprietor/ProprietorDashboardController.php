<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\AiSummary;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Tenant;

class ProprietorDashboardController extends Controller
{
    public function index()
{
    // Tenant overview
    $totalTenants = Tenant::count();

    // Occupancy overview
    $totalBeds = Bed::count();
    $occupiedBeds = Bed::where('status', 'occupied')->count();

    $occupancyRate = $totalBeds > 0
        ? round(($occupiedBeds / $totalBeds) * 100, 1)
        : 0;

    // Financial overview
    $totalCollectedThisMonth = Payment::where('status', 'paid')
        ->whereMonth('paid_date', now()->month)
        ->whereYear('paid_date', now()->year)
        ->sum('amount');

    $totalUnpaid = Payment::whereIn('status', ['pending', 'overdue'])
        ->sum('amount');

    // Complaint overview
    $unresolvedComplaintsCount = Complaint::whereIn(
        'status',
        ['submitted', 'in_progress']
    )->count();

    // High-risk tenants
    $highRiskTenants = Tenant::with(['user', 'latestRiskFlag'])
        ->get()
        ->filter(
            fn (Tenant $tenant) =>
                $tenant->latestRiskFlag?->risk_level === 'high'
        )
        ->values();

    // Pending + overdue payment count
    $pendingAndOverduePaymentsCount = Payment::whereIn(
        'status',
        ['pending', 'overdue']
    )->count();

    // Recent AI summaries
    $recentAiSummaries = AiSummary::with('generatedBy')
        ->latest()
        ->take(3)
        ->get();

    return view('proprietor.dashboard', compact(
        'totalTenants',
        'totalBeds',
        'occupiedBeds',
        'occupancyRate',
        'totalCollectedThisMonth',
        'totalUnpaid',
        'unresolvedComplaintsCount',
        'highRiskTenants',
        'pendingAndOverduePaymentsCount',
        'recentAiSummaries'
    ));
}
}
