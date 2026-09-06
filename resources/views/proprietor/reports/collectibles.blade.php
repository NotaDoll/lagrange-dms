@extends('layouts.proprietor')

@section('title', 'Collectibles')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Collectibles</h1>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Tenants with Outstanding Balance</div>
                    <div class="display-6">{{ $tenants->count() }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Outstanding (Pending + Overdue)</div>
                    <div class="display-6">PHP {{ number_format((float) $grandTotal, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Outstanding Balances by Tenant
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Pending</th>
                            <th>Overdue</th>
                            <th>Worst Days Overdue</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row->tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $row->tenant->user->email }}</div>
                                </td>
                                <td>{{ $row->tenant->currentAssignment?->bed?->room?->room_number ?? '—' }}</td>
                                <td>PHP {{ number_format((float) $row->total_pending, 2) }}</td>
                                <td>
                                    @if ($row->total_overdue > 0)
                                        <span class="text-danger fw-semibold">PHP {{ number_format((float) $row->total_overdue, 2) }}</span>
                                    @else
                                        PHP 0.00
                                    @endif
                                </td>
                                <td>{{ $row->max_days_overdue ?? 0 }}</td>
                                <td>
                                    <a href="{{ route('proprietor.payments.index', ['tenant_id' => $row->tenant->id]) }}" class="btn btn-sm btn-outline-secondary">
                                        View Payments
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No outstanding balances. Everyone's paid up.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection