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

        $tenants = Tenant::with(['user', 'latestRiskFlag'])
            ->get()
            ->sortBy(fn (Tenant $tenant) => $riskOrder[$tenant->latestRiskFlag?->risk_level] ?? 3)
            ->values();

        return view('proprietor.risk.index', compact('tenants'));
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
