@extends('layouts.tenant')

@section('title', 'My Payments')

@section('content')
    @php
        $statusClass = match (strtolower($paymentStatus)) {
            'overdue' => 'red',
            'pending' => 'orange',
            default => 'green',
        };
    @endphp

    <div class="theme-page-header mb-4">
        <h1>My Payments</h1>
        <p>Review your balance, payment standing, and transaction history.</p>
    </div>

    <div class="theme-stat-grid">
        <div class="theme-stat-card">
            <div class="theme-stat-label">Outstanding Balance</div>
            <div class="theme-stat-value">PHP {{ number_format((float) $outstandingBalance, 2) }}</div>
            <div class="theme-stat-sub {{ $outstandingCount > 0 ? 'red' : 'muted' }}">
                {{ $outstandingCount }} {{ Str::plural('unpaid transaction', $outstandingCount) }}
            </div>
        </div>

        <div class="theme-stat-card">
            <div class="theme-stat-label">Payment Status</div>
            <div class="theme-stat-value">{{ $paymentStatus }}</div>
            <div class="theme-stat-sub {{ $statusClass }}">
                {{ $paymentStatus === 'Paid' ? 'No pending payments' : 'Action may be required' }}
            </div>
        </div>

        <div class="theme-stat-card">
            <div class="theme-stat-label">Total Paid</div>
            <div class="theme-stat-value">PHP {{ number_format((float) $totalPaid, 2) }}</div>
            <div class="theme-stat-sub green">
                {{ $paidCount }} {{ Str::plural('completed payment', $paidCount) }}
            </div>
        </div>
    </div>

    <div class="theme-card">
        <div class="theme-card-header">
            <h2 class="theme-card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16v16H4z"></path>
                    <path d="M8 8h8"></path>
                    <path d="M8 12h8"></path>
                    <path d="M8 16h5"></path>
                </svg>
                Transaction History
            </h2>
        </div>

        <div class="table-responsive">
            <table class="table theme-table mb-0">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td data-label="Receipt No.">PAY-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td data-label="Description">
                                {{ ucfirst($payment->status) }} dorm payment due {{ $payment->due_date->format('M d, Y') }}
                            </td>
                            <td data-label="Amount">PHP {{ number_format((float) $payment->amount, 2) }}</td>
                            <td data-label="Date">{{ ($payment->paid_date ?? $payment->due_date)->format('M d, Y') }}</td>
                            <td data-label="">
                                <a href="#" class="theme-link-pill">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($payments->total() > 0)
        <div class="theme-pagination">
            <div>
                Showing <strong>{{ $payments->firstItem() ?? 0 }}-{{ $payments->lastItem() ?? 0 }}</strong> of {{ $payments->total() }} transactions
            </div>
            <div class="theme-pager">
                @if ($payments->onFirstPage())
                    <span class="theme-pager-btn disabled">&#8592;</span>
                @else
                    <a href="{{ $payments->previousPageUrl() }}" class="theme-pager-btn">&#8592;</a>
                @endif

                <span class="theme-pager-current">{{ $payments->currentPage() }}</span>

                @if ($payments->hasMorePages())
                    <a href="{{ $payments->nextPageUrl() }}" class="theme-pager-btn">&#8594;</a>
                @else
                    <span class="theme-pager-btn disabled">&#8594;</span>
                @endif
            </div>
        </div>
    @endif
@endsection
