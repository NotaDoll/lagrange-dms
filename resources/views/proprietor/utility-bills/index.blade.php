@extends('layouts.proprietor')

@section('title', 'Utility Bills')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Utility Bills</h1>
        <a href="{{ route('proprietor.utility-bills.create') }}" class="btn btn-primary">Add Utility Bill</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.utility-bills.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="room_id" class="form-label">Room</label>
                    <select class="form-select" id="room_id" name="room_id">
                        <option value="">All rooms</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected((string) $roomId === (string) $room->id)>
                                {{ $room->room_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('proprietor.utility-bills.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bills as $bill)
                            <tr>
                                <td>{{ $bill->room->room_number }}</td>
                                <td>{{ ucfirst($bill->bill_type) }}</td>
                                <td>{{ $bill->billing_period_start->format('M d') }} – {{ $bill->billing_period_end->format('M d, Y') }}</td>
                                <td>PHP {{ number_format((float) $bill->amount, 2) }}</td>
                                <td>{{ $bill->due_date->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge text-bg-{{ ['pending' => 'secondary', 'paid' => 'success', 'overdue' => 'danger'][$bill->status] }}">
                                        {{ ucfirst($bill->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('proprietor.utility-bills.edit', $bill) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No utility bills found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $bills->links() }}</div>
        </div>
    </div>
@endsection