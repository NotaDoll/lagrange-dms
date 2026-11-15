@extends('layouts.proprietor')

@section('title', 'Payment History - ' . $tenant->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Payment History</h1>
            <p class="text-muted mb-0">Tenant: <strong>{{ $tenant->name }}</strong> ({{ $tenant->email }})</p>
            @if ($bed = $tenant->currentAssignment?->bed)
                <p class="text-muted small">Location: {{ $bed->locationLabel() }}</p>
            @endif
        </div>
        <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary">Back to Ledger</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Paid Date</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                            <th>Receipt No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->description }}</td>
                                <td>PHP {{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->due_date->format('M d, Y') }}</td>
                                <td>{{ $payment->paid_date?->format('M d, Y') ?? 'Not paid' }}</td>
                                <td>{{ $payment->days_overdue }}</td>
                                <td>
                                    <span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$payment->status] }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($payment->receipt_number)
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ $payment->receipt_number }}
                                        </span>
                                    @else
                                        <span class="text-muted small fst-italic">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No payment history found for this tenant.</td>
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
