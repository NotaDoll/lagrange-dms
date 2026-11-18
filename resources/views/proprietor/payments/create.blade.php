@extends('layouts.proprietor')

@section('title', 'Record Payment')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Record Payment</h1>
        <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <p class="text-muted small">Record money received from a tenant.</p><div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.payments.store') }}" id="record-payment-form" data-balance-url="{{ route('proprietor.payments.month-balance') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tenant_id" class="form-label">Tenant</label>
                        <select class="form-select @error('tenant_id') is-invalid @enderror" id="tenant_id" name="tenant_id" required data-searchable data-placeholder="Search tenant by name or email">
                            <option value="">Select tenant</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected((string) old('tenant_id', $selectedTenantId) === (string) $tenant->id)>
                                    {{ $tenant->name }} - {{ $tenant->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('tenant_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="payment_type" class="form-label">Payment Type</label>
                        <select class="form-select @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                            @foreach ([
                                'full' => ['Full', 'Pays the whole balance of the oldest unpaid month.'],
                                'partial' => ['Partial', 'Pays part of the oldest unpaid month; the rest stays unpaid.'],
                                'advance' => ['Advance', 'Pays upcoming months ahead of time.'],
                            ] as $value => [$label, $hint])
                                <option value="{{ $value }}" data-hint="{{ $hint }}" @selected(old('payment_type', 'full') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="payment_type_hint"></div>
                        @error('payment_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" min="0.01" step="0.01" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="receipt_number" class="form-label">Receipt No.</label>
                        <input type="text" class="form-control @error('receipt_number') is-invalid @enderror" id="receipt_number" name="receipt_number" value="{{ old('receipt_number') }}" maxlength="50" placeholder="e.g. 0123" autocomplete="off" required>
                        <div class="form-text">The number on the receipt you gave the tenant.</div>
                        @error('receipt_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="paid_date" class="form-label">Paid Date</label>
                        <input type="date" class="form-control @error('paid_date') is-invalid @enderror" id="paid_date" name="paid_date" value="{{ old('paid_date', \App\Models\Tenant::localDate()) }}" max="{{ \App\Models\Tenant::localDate() }}" required>
                        @error('paid_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border small mb-0 d-none" id="month-balance" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@include('partials.searchable-select')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('record-payment-form');
    const tenant = document.getElementById('tenant_id');
    const amount = document.getElementById('amount');
    const typeSelect = document.getElementById('payment_type');
    const box = document.getElementById('month-balance');
    const peso = (n) => 'PHP ' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let request = 0;

    const show = (html, warn = false) => {
        box.innerHTML = html;
        box.classList.toggle('alert-warning', warn);
        box.classList.toggle('alert-light', !warn);
        box.classList.remove('d-none');
    };

    const render = (b) => {
        const type = typeSelect.value;
        if (!b.ok) {
            show(b.message, true);
            return;
        }
        let text = `${b.month}: ${b.storage ? 'storage fee' : 'rent'} ${peso(b.amount)}, paid ${peso(b.paid)}, balance <strong>${peso(b.remaining)}</strong>.`;
        if (type === 'partial') text += b.storage ? ' Storage fees must be paid in full.' : ' Enter less than the balance.';
        if (type === 'advance') text += ' Any extra amount is applied to the following months in order.';
        show(text);
        if (type === 'full') amount.value = b.remaining.toFixed(2);
    };

    const load = async () => {
        const hint = typeSelect.selectedOptions[0]?.dataset.hint || '';
        document.getElementById('payment_type_hint').textContent = hint;
        if (!tenant.value) { box.classList.add('d-none'); return; }
        const id = ++request;
        const url = new URL(form.dataset.balanceUrl, window.location.origin);
        url.search = new URLSearchParams({ tenant_id: tenant.value, payment_type: typeSelect.value });
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (res.ok && id === request) render(await res.json());
        } catch (e) { /* the server still checks everything on save */ }
    };

    ['change', 'tom-change'].forEach((evt) => tenant.addEventListener(evt, load));
    typeSelect.addEventListener('change', load);
    load();
});
</script>
@endpush
