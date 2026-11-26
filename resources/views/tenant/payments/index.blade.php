@extends('layouts.tenant')

@section('title', 'My Payments')

@section('content')
    @php
        $statusClass = match (strtolower($paymentStatus)) {
            'overdue' => 'red',
            'outstanding' => 'red',
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
            <div class="theme-stat-sub {{ $unpaidMonths > 0 ? 'red' : 'muted' }}">
                {{ $unpaidMonths }} {{ Str::plural('billing month with unpaid charges', $unpaidMonths) }} · includes utility shares
            </div>
            @include('tenant.current-rent')
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
                @if ($advancePayment > 0)
                    <div>Includes PHP {{ number_format($advancePayment, 2) }} in advance rent</div>
                @endif
            </div>
        </div>
    </div>

    @if ($checkoutTableReady)
    @php $gcashLimit = min((float) $gcashBalance, 100000); @endphp
    <div class="theme-card mb-4">
        <div class="theme-card-header">
            <h2 class="theme-card-title">Pay with GCash</h2>
        </div>
        <div class="p-4">
            @if (session('status'))
                <div class="alert alert-info" role="status">{{ session('status') }}</div>
            @endif
            @if ($gcashBalance > 0)
                <p>Pay rent and storage charges securely through Xendit. You’ll be redirected to complete payment in GCash.</p>
                <form method="POST" action="{{ route('tenant.payments.gcash') }}" class="d-flex flex-wrap align-items-end gap-3">
                    @csrf
                    <div>
                        <label for="gcash-amount" class="form-label">Amount (PHP)</label>
                        <input id="gcash-amount" name="amount" type="number" min="1" max="{{ number_format($gcashLimit, 2, '.', '') }}" step="0.01" value="{{ number_format($gcashLimit, 2, '.', '') }}" required class="form-control">
                        @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
                        @error('gcash') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Continue to GCash</button>
                </form>
            @else
                <p class="mb-0">You have no unpaid rent or storage charges available for GCash. Utility balances can still be paid through your usual arrangement.</p>
            @endif
            @if ($recentCheckouts->isNotEmpty())
                <div class="mt-3 small text-muted">
                    @foreach ($recentCheckouts as $checkout)
                        <div>GCash · PHP {{ number_format((float) $checkout->amount, 2) }} · {{ ucfirst($checkout->status) }} · {{ $checkout->created_at->format('M d, Y') }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif

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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td data-label="Receipt No.">{{ $payment->display_receipt ?? ($payment->exists ? 'PAY-'.str_pad($payment->id, 6, '0', STR_PAD_LEFT) : '—') }}</td>
                            <td data-label="Description">
                                {{ $payment->status_label }} · {{ $payment->description }} due {{ $payment->due_date->format('M d, Y') }}
                            </td>
                            <td data-label="Amount">PHP {{ number_format((float) $payment->amount, 2) }}</td>
                            <td data-label="Date">{{ ($payment->paid_date ?? $payment->due_date)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">No payments found.</td>
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

