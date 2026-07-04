<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $collectedQuery = Payment::query()->where('status', 'paid');

        if ($dateFrom) {
            $collectedQuery->whereDate('paid_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $collectedQuery->whereDate('paid_date', '<=', $dateTo);
        }

        if (! $dateFrom && ! $dateTo) {
            $collectedQuery
                ->whereMonth('paid_date', Carbon::now()->month)
                ->whereYear('paid_date', Carbon::now()->year);
        }

        $totalCollected = (float) $collectedQuery->sum('amount');
        $totalPending = (float) Payment::where('status', 'pending')->sum('amount');
        $totalOverdue = (float) Payment::where('status', 'overdue')->sum('amount');

        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0;

        $paymentBreakdown = Payment::query()
            ->selectRaw('status, COUNT(*) as payment_count, COALESCE(SUM(amount), 0) as total_amount')
            ->when($dateFrom, fn ($query) => $query->whereDate('due_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('due_date', '<=', $dateTo))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->keyBy('status');

        return view('proprietor.reports.index', compact(
            'dateFrom',
            'dateTo',
            'totalCollected',
            'totalPending',
            'totalOverdue',
            'totalBeds',
            'occupiedBeds',
            'occupancyRate',
            'paymentBreakdown'
        ));
    }
}
