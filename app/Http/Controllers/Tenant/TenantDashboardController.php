<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\TenantBalanceService;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function index(TenantBalanceService $balanceService): View
    {
        $tenant = auth()->user()->tenant()->firstOrFail();
        $balance = $balanceService->summary($tenant);
        $rentTerms = $balanceService->currentRent($tenant);
        $outstandingBalance = $balance['outstanding_balance'];

        $announcementsCount = Announcement::count();
        $latestAnnouncement = Announcement::latest()->first();

        return view('tenant.dashboard', compact(
            'rentTerms',
            'outstandingBalance',
            'announcementsCount',
            'latestAnnouncement'
        ));
    }
}
