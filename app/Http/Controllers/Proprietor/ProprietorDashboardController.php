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
        $highRiskTenants = Tenant::with(['user', 'latestRiskFlag'])
            ->get()
            ->filter(fn (Tenant $tenant) => $tenant->latestRiskFlag?->risk_level === 'high')
            ->values();

        $unresolvedComplaintsCount = Complaint::whereIn('status', ['submitted', 'in_progress'])->count();

        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

        $pendingAndOverduePaymentsCount = Payment::whereIn('status', ['pending', 'overdue'])->count();

        $recentAiSummaries = AiSummary::with('generatedBy')
            ->latest()
            ->take(3)
            ->get();

        return view('proprietor.dashboard', compact(
            'highRiskTenants',
            'unresolvedComplaintsCount',
            'occupiedBeds',
            'totalBeds',
            'occupancyRate',
            'pendingAndOverduePaymentsCount',
            'recentAiSummaries'
        ));
    }
}
