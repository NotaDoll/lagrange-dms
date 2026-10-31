@extends('layouts.proprietor')
@section('title', 'Utility Bills')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h1 class="h3 mb-1">Utility Bills</h1><p class="text-muted mb-0">Record room bills and track each tenant’s share.</p></div>
        <a href="{{ route('proprietor.utility-bills.create') }}" class="btn btn-primary">Record Room Bill</a>
    </div>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="card mb-4"><div class="card-body">
        <form method="GET" class="row g-3 align-items-end" action="{{ route('proprietor.utility-bills.index') }}">
            <div class="col-md-3"><label for="room_id" class="form-label">Room</label><select class="form-select" id="room_id" name="room_id"><option value="">All rooms</option>@foreach ($rooms as $room)<option value="{{ $room->id }}" @selected((string) $roomId === (string) $room->id)>{{ $room->room_number }}</option>@endforeach</select></div>
            <div class="col-md-3"><label for="bill_type" class="form-label">Bill type</label><select class="form-select" id="bill_type" name="bill_type"><option value="">All types</option>@foreach (['electricity', 'water', 'internet', 'other'] as $type)<option value="{{ $type }}" @selected($billType === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label for="status" class="form-label">Provider payment</label><select class="form-select" id="status" name="status"><option value="">All statuses</option>@foreach (['pending' => 'Unpaid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('proprietor.utility-bills.index') }}" class="btn btn-outline-primary">Reset</a></div>
        </form>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle">
            <caption class="visually-hidden">Room utility bills and tenant collection balances</caption>
            <thead><tr><th scope="col">Room / Bill</th><th scope="col">Billing period</th><th scope="col" class="text-end">Room total</th><th scope="col">Due / Provider</th><th scope="col">Tenant collections</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                @forelse ($bills as $bill)
                    @php
                        $collected = (float) $bill->payments->where('status', 'paid')->sum('amount');
                        $remaining = (float) $bill->payments->whereIn('status', ['pending', 'overdue'])->sum('amount');
                    @endphp
                    <tr>
                        <td><strong>Room {{ $bill->room->room_number }}</strong><div class="small text-muted">{{ $bill->label }} · #{{ $bill->id }}</div></td>
                        <td class="small">{{ $bill->billing_period_start->format('M j, Y') }}<br>{{ $bill->billing_period_end->format('M j, Y') }}</td>
                        <td class="text-end text-nowrap fw-semibold">₱{{ number_format((float) $bill->amount, 2) }}</td>
                        <td><div class="small mb-1">{{ $bill->due_date->format('M j, Y') }}</div><span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$bill->status] }}">{{ $bill->status === 'pending' ? 'Unpaid' : ucfirst($bill->status) }}</span></td>
                        <td>
                            <div class="small">₱{{ number_format($collected, 2) }} collected</div>
                            <div class="small {{ $remaining > 0 ? 'text-danger' : 'text-muted' }}">₱{{ number_format($remaining, 2) }} outstanding</div>
                            <details class="small mt-2"><summary>{{ $bill->payments->count() }} tenant shares</summary>
                                <ul class="list-unstyled mt-2 mb-0">
                                    @forelse ($bill->payments as $share)
                                        <li class="py-1">{{ $share->tenant->user->name }} — ₱{{ number_format((float) $share->amount, 2) }} <span class="badge text-bg-{{ $share->status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($share->status) }}</span> @if ($share->status !== 'paid')<a href="{{ route('proprietor.payments.index', ['tenant_id' => $share->tenant_id]) }}">Record collection</a>@endif</li>
                                    @empty
                                        <li class="text-muted">No shares allocated. Edit and save this bill to allocate it.</li>
                                    @endforelse
                                </ul>
                            </details>
                        </td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('proprietor.utility-bills.edit', $bill) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No utility bills match these filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="mt-3">{{ $bills->links() }}</div>
        <p class="small text-muted mb-0">Room totals are split equally among tenants assigned during each billing period. Tenant payments are recorded in Payment Ledger; provider payment status is tracked separately.</p>
    </div></div>
@endsection
