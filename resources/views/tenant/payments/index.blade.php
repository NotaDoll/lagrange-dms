@extends('layouts.tenant')

@section('title', 'My Payments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">My Payments</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
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
                                <td colspan="5" class="text-center text-muted py-4">No payments found.</td>
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
