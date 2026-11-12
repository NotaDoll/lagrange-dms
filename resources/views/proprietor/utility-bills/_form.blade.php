@csrf
@php
    $bill = $utilityBill ?? null;
    $hasCollections = $bill?->payments->contains('status', 'paid') ?? false;
    $savedPreview = $bill ? $bill->payments->map(fn ($payment) => ['tenant_id' => $payment->tenant_id, 'name' => $payment->tenant->name, 'cents' => (int) round((float) $payment->amount * 100)])->values() : [];
    $originalAllocation = $bill ? [
        'room_id' => $bill->room_id, 'bill_type' => $bill->bill_type, 'other_description' => $bill->other_description,
        'billing_period_start' => $bill->billing_period_start->toDateString(), 'billing_period_end' => $bill->billing_period_end->toDateString(),
        'amount' => $bill->amount, 'due_date' => $bill->due_date->toDateString(),
    ] : null;
@endphp
@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<p class="text-muted">Choose a room and bill type. The room total is split equally among tenants assigned during the billing period. Shares are added to their balances when you save.</p>
@if ($hasCollections)
    <div class="alert alert-info">This bill has tenant collections. You can update provider payment status and notes; billing details are protected.</div>
@endif
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="room_id">1. Room</label>
        <select class="form-select" name="room_id" id="room_id" required>
            <option value="">Choose room</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected((string) old('room_id', $bill?->room_id) === (string) $room->id)>Room {{ $room->room_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="bill_type">2. Bill type</label>
        <select class="form-select" name="bill_type" id="bill_type" required>
            <option value="">Choose type</option>
            @foreach (['electricity' => 'Electricity', 'water' => 'Water', 'internet' => 'Internet', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(old('bill_type', $bill?->bill_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12" id="other-description-field">
        <label class="form-label" for="other_description">Other bill name</label>
        <input class="form-control" id="other_description" name="other_description" maxlength="100" value="{{ old('other_description', $bill?->other_description) }}" placeholder="e.g. Garbage collection">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="billing_period_start">Billing period from</label>
        <input class="form-control" type="date" id="billing_period_start" name="billing_period_start" value="{{ old('billing_period_start', $bill?->billing_period_start?->toDateString() ?? now()->startOfMonth()->toDateString()) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="billing_period_end">Billing period to</label>
        <input class="form-control" type="date" id="billing_period_end" name="billing_period_end" value="{{ old('billing_period_end', $bill?->billing_period_end?->toDateString() ?? now()->endOfMonth()->toDateString()) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="amount">Room bill total (₱)</label>
        <input class="form-control" type="number" min="0.01" max="99999999.99" step="0.01" id="amount" name="amount" value="{{ old('amount', $bill?->amount) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="due_date">Tenant payment due date</label>
        <input class="form-control" type="date" id="due_date" name="due_date" value="{{ old('due_date', $bill?->due_date?->toDateString() ?? now()->endOfMonth()->toDateString()) }}" required>
    </div>
    <div class="col-12">
        <div class="card bg-light border-0"><div class="card-body">
            <h2 class="h6">Tenant share preview</h2>
            <p class="small text-muted">Equal shares; any remaining centavos go to tenants in ID order. Tenants with an overlapping assignment are included, even if they have moved out. If storage applies, a bill spanning multiple months is divided by days before allocating monthly shares.</p>
            <div id="share-preview" class="small" aria-live="polite">Choose a room, billing period and amount to preview shares.</div>
        </div></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Payment to utility provider</label>
        <select class="form-select" id="status" name="status" required>
            @foreach (['pending' => 'Unpaid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $bill?->status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">Provider payment does not clear tenant shares. Use Mark paid in Payment Ledger when a tenant pays.</div>
    </div>
    <div class="col-md-6" id="provider-paid-date-field">
        <label class="form-label" for="paid_date">Date paid to provider</label>
        <input class="form-control" type="date" id="paid_date" name="paid_date" max="{{ today()->toDateString() }}" value="{{ old('paid_date', $bill?->paid_date?->toDateString()) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes (optional)</label>
        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $bill?->notes) }}</textarea>
    </div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('proprietor.utility-bills.index') }}" class="btn btn-outline-primary">Cancel</a>
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</div>
@push('scripts')
<script>
(() => {
    const assignments = @json($roomAssignments);
    const billingStart = @json($billingStart);
    const saved = @json($savedPreview);
    const original = @json($originalAllocation);
    const get = id => document.getElementById(id);
    const money = cents => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(cents / 100);
    function overlaps(assignment, start, end) {
        return assignment.start <= end && (!assignment.end || (assignment.end >= start && assignment.end >= assignment.start));
    }

    function dayNumber(date) {
        return Date.parse(date + 'T00:00:00Z') / 86400000;
    }

    function dayString(day) {
        return new Date(day * 86400000).toISOString().slice(0, 10);
    }

    function periods(start, end) {
        const months = [];
        let first = dayNumber(start);
        const last = dayNumber(end);
        while (first <= last) {
            const key = dayString(first).slice(0, 7);
            const nextMonth = new Date(key + '-01T00:00:00Z');
            nextMonth.setUTCMonth(nextMonth.getUTCMonth() + 1);
            const finalDay = Math.min(last, nextMonth.getTime() / 86400000 - 1);
            months.push({key, start: dayString(first), end: dayString(finalDay), days: finalDay - first + 1});
            first = finalDay + 1;
        }
        return months;
    }

    function sharesFor(assigned, start, end, cents) {
        const shares = new Map();
        function add(occupants, amount) {
            const tenants = [...new Map(occupants.map(a => [a.tenant_id, a])).values()].sort((a, b) => a.tenant_id - b.tenant_id);
            if (amount < tenants.length) return false;
            tenants.forEach((tenant, index) => {
                const share = Math.floor(amount / tenants.length) + (index < amount % tenants.length ? 1 : 0);
                shares.set(tenant.tenant_id, {...tenant, cents: (shares.get(tenant.tenant_id)?.cents ?? 0) + share});
            });
            return true;
        }

        const months = periods(start, end);
        const mixed = months.some(month => assigned.some(a => a.storage.includes(month.key) && overlaps(a, month.start, month.end)));
        if (!mixed) return add(assigned, cents) ? [...shares.values()] : null;

        const totalDays = dayNumber(end) - dayNumber(start) + 1;
        let elapsed = 0;
        for (const month of months) {
            const amount = Math.floor(cents * (elapsed + month.days) / totalDays) - Math.floor(cents * elapsed / totalDays);
            elapsed += month.days;
            const regular = assigned.filter(a => !a.storage.includes(month.key) && overlaps(a, month.start, month.end));
            if (!add(regular, amount)) return null;
        }
        return [...shares.values()].sort((a, b) => a.tenant_id - b.tenant_id);
    }

    function preview() {
        const room = get('room_id').value;
        const start = get('billing_period_start').value;
        const end = get('billing_period_end').value;
        const cents = Math.round(Number(get('amount').value) * 100);
        const target = get('share-preview');
        target.replaceChildren();
        if (!room || !start || !end || end < start || cents <= 0) {
            target.textContent = 'Choose a room, valid billing period and amount to preview shares.';
            return;
        }
        const unchanged = original && saved.length && Object.entries(original).every(([key, value]) => key === 'amount'
            ? Math.round(Number(get(key).value) * 100) === Math.round(Number(value) * 100)
            : get(key).value === String(value ?? ''));
        const eligibleStart = start < billingStart ? billingStart : start;
        const overlapping = eligibleStart <= end
            ? assignments.filter(a => String(a.room_id) === room && overlaps(a, eligibleStart, end)) : [];
        const tenants = unchanged ? saved : sharesFor(overlapping, eligibleStart, end, cents);
        if (!tenants) {
            target.textContent = 'Enter an amount that provides at least ₱0.01 for each assigned tenant.';
            return;
        }
        if (!tenants.length) {
            target.textContent = end < billingStart ? 'This period ends before the billing start date.'
                : overlapping.length ? 'The provider bill can be saved. No tenant shares will be generated for this storage period.'
                : 'No tenants were assigned to this room during the billable period.';
            return;
        }
        for (const tenant of tenants) {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between border-bottom py-2';
            const name = document.createElement('span');
            name.textContent = tenant.name;
            const share = document.createElement('strong');
            share.textContent = money(tenant.cents);
            row.append(name, share);
            target.append(row);
        }
    }
    function toggleFields() {
        const other = get('bill_type').value === 'other';
        get('other-description-field').hidden = !other;
        get('other_description').required = other;
        const paid = get('status').value === 'paid';
        get('provider-paid-date-field').hidden = !paid;
        get('paid_date').required = paid;
        get('paid_date').disabled = !paid;
    }
    for (const id of ['room_id', 'bill_type', 'other_description', 'billing_period_start', 'billing_period_end', 'amount', 'due_date']) get(id).addEventListener('input', preview);
    for (const id of ['bill_type', 'status']) get(id).addEventListener('change', toggleFields);
    preview();
    toggleFields();
})();
</script>
@endpush
