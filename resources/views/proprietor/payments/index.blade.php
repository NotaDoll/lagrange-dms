@extends('layouts.proprietor')

@section('title', 'Payment Ledger')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Payment Ledger</h1>

        <div class="d-flex gap-2">
            <a href="{{ route('proprietor.risk.index') }}" class="btn btn-outline-danger"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Risk Dashboard</a>
            <button type="button" onclick="document.getElementById('excel-import-input').click();" class="btn btn-outline-secondary">
                <i class="bi bi-upload" aria-hidden="true"></i> Import Excel
            </button>
            <form action="{{ route('proprietor.payments.import') }}" method="POST" enctype="multipart/form-data" id="import-form" class="d-none">
                @csrf
                <input type="file" name="excel_file" id="excel-import-input" accept=".xlsx, .xls, .csv" onchange="document.getElementById('import-form').submit();">
            </form>
            <a href="{{ route('proprietor.payments.create') }}" class="btn btn-primary"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record Payment</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>{{ session('warning') }}</strong>
            <ul class="mb-0 mt-2">
                @foreach(session('import_errors', []) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <p class="text-muted small">Recorded charges and payments feed Financial Report automatically. Mark an existing charge paid when collected.</p>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.payments.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id" data-searchable data-placeholder="Search tenant">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) $tenantId === (string) $tenant->id)>
                                {{ ucwords(strtolower($tenant->name)) }}{{ ($riskLevels[$tenant->id] ?? null) === 'high' ? ' (High risk)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Payment::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filter</button>
                    <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Paid Date</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                            <th>Receipt No.</th>
                            <th><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <!-- FIXED: Swapped to double quotes for HTML attribute to prevent JS syntax error -->
                            <tr onclick="window.location='{{ route('proprietor.payments.history', $payment->tenant->id) }}'" style="cursor: pointer;">
                                <td>
                                    <!-- FIXED: Added ucwords to format the name properly -->
                                    <div class="fw-semibold">{{ ucwords(strtolower($payment->tenant->name)) }}</div>
                                    <div class="text-muted small">{{ $payment->tenant->email }}</div>
                                    @if ($bed = $payment->tenant->currentAssignment?->bed)
                                        <div class="text-muted small">{{ $bed->locationLabel() }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $payment->description }}<div class="small text-muted">{{ $categories[$payment->category] }}</div>
                                    @if (filled($payment->note))
                                        <div class="small text-break">{{ $payment->note }}</div>
                                    @endif
                                </td>
                                <td>PHP {{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->due_date->format('M d, Y') }}</td>
                                <td>{{ $payment->paid_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $payment->current_days_overdue }}</td>
                                <td>
                                    <span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$payment->current_status] }}">
                                        {{ $payment->status_label }}
                                    </span>
                                    @if ($payment->payment_type)
                                        <div class="small text-muted mt-1">{{ \App\Models\Payment::PAYMENT_TYPE_LABELS[$payment->payment_type] ?? ucfirst($payment->payment_type) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($payment->display_receipt)
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ $payment->display_receipt }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td onclick="event.stopPropagation()" style="cursor: default;">
                                    @if (! $payment->hasRecordedPayment() && ! $payment->exists && $payment->category === \App\Models\Payment::RENT)
                                        <a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ route('proprietor.payments.create', ['tenant_id' => $payment->tenant_id]) }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record payment</a>
                                    @elseif (! $payment->hasRecordedPayment() && $payment->category === \App\Models\Payment::UTILITIES)
                                        <a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ route('proprietor.payments.create', ['tenant_id' => $payment->tenant_id, 'utility_payment_id' => $payment->id]) }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record payment</a>
                                    @elseif (! $payment->hasRecordedPayment())
                                        @php($settleId = $payment->exists ? 'settle-'.$payment->id : 'accrued-'.$payment->tenant_id.'-'.$payment->category.'-'.$payment->due_date->toDateString())
                                        <form method="POST" action="{{ $payment->exists ? route('proprietor.payments.settle', $payment) : route('proprietor.payments.accrued.settle', $payment->tenant) }}" class="d-flex gap-2 align-items-center">
                                            @csrf
                                            @method('PATCH')
                                            @unless ($payment->exists)
                                                <input type="hidden" name="charge_type" value="{{ $payment->charge_type }}">
                                                <input type="hidden" name="due_date" value="{{ $payment->due_date->toDateString() }}">
                                            @endunless
                                            <label class="visually-hidden" for="{{ $settleId }}">Payment date for {{ $payment->tenant->name }}, {{ $payment->description }}</label>
                                            <input type="date" class="form-control form-control-sm" id="{{ $settleId }}" name="paid_date" value="{{ \App\Models\Tenant::localDate() }}" max="{{ \App\Models\Tenant::localDate() }}" required>
                                            <label class="visually-hidden" for="{{ $settleId }}-receipt">Receipt number</label>
                                            <input type="text" class="form-control form-control-sm" id="{{ $settleId }}-receipt" name="receipt_number" placeholder="Receipt no." maxlength="50" style="width: 7.5rem;" autocomplete="off" required>
                                            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap"><i class="bi bi-check2-circle" aria-hidden="true"></i> Mark paid</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No payments found.</td></tr>
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

@include('partials.searchable-select')
