<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PaymentRiskService;

class RiskDashboardController extends Controller
{
    public function __construct(private readonly PaymentRiskService $paymentRiskService)
    {
    }

    public function index()
    {
        $riskOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

        // Only active tenants are billed, so only they are risk-rated.
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->with(['user', 'latestRiskFlag', 'currentAssignment.bed.room'])
            ->get()
            ->sortBy(fn (Tenant $tenant) => $riskOrder[$tenant->latestRiskFlag?->risk_level] ?? 3)
            ->values();

        $summary = [
            'high'   => $tenants->filter(fn ($t) => $t->latestRiskFlag?->risk_level === 'high')->count(),
            'medium' => $tenants->filter(fn ($t) => $t->latestRiskFlag?->risk_level === 'medium')->count(),
            'low'    => $tenants->filter(fn ($t) => $t->latestRiskFlag?->risk_level === 'low')->count(),
            'unrated' => $tenants->filter(fn ($t) => $t->latestRiskFlag === null)->count(),
        ];

        return view('proprietor.risk.index', compact('tenants', 'summary'));
    }

    public function recalculate()
    {
        $count = $this->paymentRiskService->calculateForAllTenants();

        return response()->json([
            'message' => 'Risk flags recalculated successfully.',
            'tenants_calculated' => $count,
        ]);
    }
}
