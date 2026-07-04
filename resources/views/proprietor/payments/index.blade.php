@extends('layouts.proprietor')

@section('title', 'Payment Ledger')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Payment Ledger</h1>
        <a href="{{ route('proprietor.payments.create') }}" class="btn btn-primary">Record Payment</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.payments.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) $tenantId === (string) $tenant->id)>
                                {{ $tenant->user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Paid Date</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $payment->tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $payment->tenant->user->email }}</div>
                                </td>
                                <td>PHP {{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->due_date->format('M d, Y') }}</td>
                                <td>{{ $payment->paid_date?->format('M d, Y') ?? 'Not paid' }}</td>
                                <td>{{ $payment->days_overdue }}</td>
                                <td>
                                    <span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$payment->status] }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
@endsection
