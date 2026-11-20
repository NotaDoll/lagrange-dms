<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\RentReminderService;
use App\Services\TenantBalanceService;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function index(TenantBalanceService $balanceService, RentReminderService $reminders): View
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        // Also checked here, so the tenant still gets the reminder on days the scheduler was not running.
        try {
            $reminders->remind($tenant);
        } catch (\Throwable $exception) {
            report($exception);
        }
        $balance = $balanceService->summary($tenant);
        $rentTerms = $balanceService->currentRent($tenant);
        $recentTransactions = $balanceService->ledger($tenant)->take(5);
        $outstandingBalance = $balance['outstanding_balance'];

        $announcementsCount = Announcement::count();
        $latestAnnouncement = Announcement::latest()->first();

        return view('tenant.dashboard', compact(
            'rentTerms',
            'outstandingBalance',
            'recentTransactions',
            'announcementsCount',
            'latestAnnouncement'
        ));
    }
}
