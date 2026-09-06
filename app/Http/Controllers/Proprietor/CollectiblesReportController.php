<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Tenant;

class CollectiblesReportController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with(['user', 'payments' => fn ($q) => $q->whereIn('status', ['pending', 'overdue'])])
            ->whereHas('payments', fn ($q) => $q->whereIn('status', ['pending', 'overdue']))
            ->get()
            ->map(function (Tenant $tenant) {
                return (object) [
                    'tenant' => $tenant,
                    'total_pending' => $tenant->payments->where('status', 'pending')->sum('amount'),
                    'total_overdue' => $tenant->payments->where('status', 'overdue')->sum('amount'),
                    'max_days_overdue' => $tenant->payments->max('days_overdue'),
                ];
            })
            ->sortByDesc('total_overdue')
            ->values();

        $grandTotal = $tenants->sum(fn ($row) => $row->total_pending + $row->total_overdue);

        return view('proprietor.reports.collectibles', compact('tenants', 'grandTotal'));
    }
}