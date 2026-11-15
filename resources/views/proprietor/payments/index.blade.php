@extends('layouts.proprietor')

@section('title', 'Payment Ledger')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Payment Ledger</h1>

        <div class="d-flex gap-2">
            <button type="button" onclick="document.getElementById('excel-import-input').click();" class="btn btn-outline-secondary">
                Import Excel
            </button>
            <form action="{{ route('proprietor.payments.import') }}" method="POST" enctype="multipart/form-data" id="import-form" class="d-none">
                @csrf
                <input type="file" name="excel_file" id="excel-import-input" accept=".xlsx, .xls, .csv" onchange="document.getElementById('import-form').submit();">
            </form>
            <a href="{{ route('proprietor.payments.create') }}" class="btn btn-primary">Record Payment</a>
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
    <a href="{{ route('proprietor.reports.index') }}" class="btn btn-outline-primary mb-3">Financial Report</a>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.payments.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="tenant_id" class="form-label">Tenant</label>
                    <select class="form-select" id="tenant_id" name="tenant_id">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) $tenantId === (string) $tenant->id)>
                                {{ ucwords(strtolower($tenant->name)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
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
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if ($accruedPayments->total())
        <section class="card mb-3" aria-labelledby="accrued-title">
            <div class="card-body">
                <h2 class="h5" id="accrued-title">Accrued storage fees and balances forward</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th scope="col">Tenant</th><th scope="col">Description</th><th scope="col">Amount</th><th scope="col">Due date</th><th scope="col">Action</th></tr></thead>
                        <tbody>
                            @foreach ($accruedPayments as $charge)
                                @php($fieldId = 'accrued-'.$charge->tenant_id.'-'.$charge->charge_type.'-'.$charge->due_date->toDateString())
                                <tr>
                                    <th scope="row">{{ ucwords(strtolower($charge->tenant->name)) }}</th>
                                    <td>{{ $charge->description }}</td>
                                    <td class="text-nowrap">PHP {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td>{{ $charge->due_date->format('M d, Y') }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('proprietor.payments.accrued.settle', $charge->tenant) }}" class="d-flex gap-2 align-items-center">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="charge_type" value="{{ $charge->charge_type }}">
                                            <input type="hidden" name="due_date" value="{{ $charge->due_date->toDateString() }}">
                                            <label class="visually-hidden" for="{{ $fieldId }}">Payment date for {{ $charge->tenant->name }}, {{ $charge->description }}</label>
                                            <input type="date" class="form-control form-control-sm" id="{{ $fieldId }}" name="paid_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                                            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">Mark paid</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $accruedPayments->links() }}
            </div>
        </section>
    @endif

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
                                <td>{{ $payment->description }}<div class="small text-muted">{{ $categories[$payment->category] }}</div></td>
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
                                        <span class="text-muted small fst-italic">Not paid</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No payments found.</td></tr>
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
