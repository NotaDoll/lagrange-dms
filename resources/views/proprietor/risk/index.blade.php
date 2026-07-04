@extends('layouts.proprietor')

@section('title', 'Payment Risk Dashboard')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Payment Risk Dashboard</h1>
        <button type="button" id="recalculate-risk" class="btn btn-primary">Recalculate Risk Flags</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Risk Level</th>
                            <th>Late Payments (6mo)</th>
                            <th>Consecutive Late Months</th>
                            <th>Avg Days Overdue</th>
                            <th>Days Since Last Default</th>
                            <th>Last Calculated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            @php
                                $riskFlag = $tenant->latestRiskFlag;
                                $riskLevel = $riskFlag?->risk_level;
                                $badgeClass = [
                                    'low' => 'success',
                                    'medium' => 'warning',
                                    'high' => 'danger',
                                ][$riskLevel] ?? 'secondary';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $tenant->user->email }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $badgeClass }}">
                                        {{ $riskLevel ? ucfirst($riskLevel) : 'Not calculated' }}
                                    </span>
                                </td>
                                <td>{{ $riskFlag?->late_payments_6mo ?? '-' }}</td>
                                <td>{{ $riskFlag?->consecutive_late_months ?? '-' }}</td>
                                <td>{{ $riskFlag ? number_format((float) $riskFlag->avg_days_overdue, 1) : '-' }}</td>
                                <td>{{ $riskFlag?->days_since_last_default ?? '-' }}</td>
                                <td>{{ $riskFlag?->calculated_at?->format('M d, Y h:i A') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No active tenants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
