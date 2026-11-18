@extends('layouts.proprietor')

@section('title', 'Record Payment')

@section('content')
    <x-breadcrumbs :items="['Payment Ledger' => route('proprietor.payments.index'), 'Record payment' => null]" />
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Record Payment</h1>
        <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
    </div>

    <p class="text-muted small">Record money received from a tenant.</p><div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.payments.store') }}" id="record-payment-form" data-preview-url="{{ route('proprietor.payments.preview') }}">
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
                        @php($appliesTo = old('applies_to', $selectedUtilityPaymentId ? 'utilities' : 'rent'))
                        <label for="applies_to" class="form-label">Apply payment to</label>
                        <select class="form-select @error('applies_to') is-invalid @enderror" id="applies_to" name="applies_to" required>
                            <option value="rent" @selected($appliesTo === 'rent')>Rent</option>
                            <option value="utilities" @selected($appliesTo === 'utilities')>Utility bill</option>
                        </select>
                        @error('applies_to')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12" id="utility-field" @if ($appliesTo !== 'utilities') hidden @endif>
                        <label for="utility_payment_id" class="form-label">Utility charge</label>
                        <select class="form-select @error('utility_payment_id') is-invalid @enderror" id="utility_payment_id" name="utility_payment_id"
                                data-selected="{{ old('utility_payment_id', $selectedUtilityPaymentId) }}" disabled>
                            <option value="">Select an unpaid charge</option>
                        </select>
                        <div class="form-text">For a new water or electricity charge, <a href="{{ route('proprietor.utility-bills.create') }}">add the utility bill</a> first.</div>
                        @error('utility_payment_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
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
                        <div class="alert alert-light border small mb-0 d-none" id="payment-preview" role="status" aria-live="polite"></div>
                    </div>

                    <div class="col-12" id="advance-field" @unless (old('apply_excess') || $errors->has('apply_excess')) hidden @endunless>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="apply_excess" id="apply_excess" value="1" @checked(old('apply_excess'))>
                            <label class="form-check-label" for="apply_excess">Apply the excess to future rent</label>
                        </div>
                        @error('apply_excess')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="note" class="form-label">Note <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="2" maxlength="1000"
                                  placeholder="Example: Tenant paid August and September rent.">{{ old('note') }}</textarea>
                        <div class="form-text">Visible to proprietors in the Payment Ledger.</div>
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('proprietor.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cash-coin" aria-hidden="true"></i> Record Payment</button>
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
    const appliesTo = document.getElementById('applies_to');
    const utilityField = document.getElementById('utility-field');
    const utility = document.getElementById('utility_payment_id');
    const advanceField = document.getElementById('advance-field');
    const advance = document.getElementById('apply_excess');
    const box = document.getElementById('payment-preview');
    const submit = form.querySelector('button[type="submit"]');
    const peso = (n) => 'PHP ' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let request = 0;
    let timer;
    let selectedUtility = utility.dataset.selected;
    let bills = [];

    const show = (content, warn = false) => {
        box.replaceChildren(content);
        box.classList.toggle('alert-warning', warn);
        box.classList.toggle('alert-light', !warn);
        box.classList.remove('d-none');
    };

    const showUtility = (prefill = false) => {
        const bill = bills.find(item => String(item.id) === utility.value);
        if (bill) {
            if (prefill) amount.value = bill.amount.toFixed(2);
            submit.disabled = Number(amount.value) !== bill.amount;
            show(`Due ${bill.due_date} · Balance ${peso(bill.amount)}. Enter the full balance for this utility charge.`);
        } else {
            submit.disabled = true;
            show(bills.length ? 'Select a utility charge.' : 'This tenant has no unpaid utility charges.');
        }
    };

    const load = async () => {
        const id = ++request;
        submit.disabled = true;
        const payingUtility = appliesTo.value === 'utilities';
        utilityField.hidden = !payingUtility;
        utility.disabled = true;
        utility.required = false;
        advanceField.hidden = true;
        advance.required = false;
        box.classList.add('d-none');
        if (!tenant.value || (!payingUtility && (!amount.value || !amount.validity.valid))) return;

        const url = new URL(form.dataset.previewUrl, window.location.origin);
        const fields = { tenant_id: tenant.value, applies_to: appliesTo.value };
        if (!payingUtility) fields.amount = amount.value;
        url.search = new URLSearchParams(fields);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const preview = await response.json();
            if (id !== request) return;
            if (!response.ok) {
                show(preview.message || 'Payment preview is unavailable.', true);
                return;
            }
            if (payingUtility) {
                bills = preview.bills;
                utility.replaceChildren(new Option('Select an unpaid charge', ''));
                for (const bill of bills) {
                    utility.add(new Option(`${bill.label} · due ${bill.due_date} · ${peso(bill.amount)}`, bill.id));
                }
                utility.value = bills.some(bill => String(bill.id) === selectedUtility) ? selectedUtility : '';
                selectedUtility = '';
                utility.disabled = false;
                utility.required = true;
                showUtility(!amount.value);
                return;
            }

            const list = document.createElement('ul');
            list.className = 'mb-0 ps-3';
            for (const allocation of preview.allocations) {
                const item = document.createElement('li');
                const balance = allocation.remaining > 0 ? ` · ${peso(allocation.remaining)} remains due` : ' · clears the balance';
                item.textContent = `${allocation.upcoming ? 'Upcoming rent' : 'Rent'} due ${allocation.due_date} · applies ${peso(allocation.amount)}${balance}`;
                list.append(item);
            }
            advanceField.hidden = !preview.allocations.some(allocation => allocation.upcoming);
            advance.required = !advanceField.hidden;
            if (advanceField.hidden) advance.checked = false;
            show(list);
            submit.disabled = false;
        } catch {
            if (id === request) show('Payment preview is unavailable. Refresh to try again.', true);
        }
    };

    const changeCharge = () => {
        clearTimeout(timer);
        advance.checked = false;
        load();
    };
    ['change', 'tom-change'].forEach(event => tenant.addEventListener(event, changeCharge));
    appliesTo.addEventListener('change', changeCharge);
    utility.addEventListener('change', () => showUtility(true));
    amount.addEventListener('input', () => {
        if (appliesTo.value === 'utilities') {
            showUtility();
            return;
        }
        request++;
        submit.disabled = true;
        advance.checked = false;
        advanceField.hidden = true;
        advance.required = false;
        box.classList.add('d-none');
        clearTimeout(timer);
        timer = setTimeout(load, 200);
    });
    load();
});
</script>
@endpush
