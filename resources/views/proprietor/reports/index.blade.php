@extends('layouts.proprietor')

@section('title', 'Financial Reports')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Financial Reports</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.reports.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="col-md-4">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="{{ route('proprietor.reports.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Collected {{ $dateFrom || $dateTo ? 'in Range' : 'This Month' }}</div>
                    <div class="display-6">PHP {{ number_format($totalCollected, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Pending</div>
                    <div class="display-6">PHP {{ number_format($totalPending, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Overdue</div>
                    <div class="display-6">PHP {{ number_format($totalOverdue, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Occupancy Rate</div>
                    <div class="display-6">{{ number_format($occupancyRate, 2) }}%</div>
                    <div class="text-muted small">{{ $occupiedBeds }} of {{ $totalBeds }} beds occupied</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Payment Breakdown
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Payment Count</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $status => $label)
                            @php
                                $row = $paymentBreakdown->get($status);
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$status] }}">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td>{{ $row?->payment_count ?? 0 }}</td>
                                <td>PHP {{ number_format((float) ($row?->total_amount ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
