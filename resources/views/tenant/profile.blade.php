@extends('layouts.tenant')

@section('title', 'My Profile')

@section('content')
    @php
        $assignment = $tenant->currentAssignment;
        $bed = $assignment?->bed;
        $room = $bed?->room;
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">My Profile</h1>
        <span class="badge {{ $tenant->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
            {{ ucfirst($tenant->status) }}
        </span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h2 class="h5">Account</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7">{{ $tenant->user->name }}</dd>
                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7">{{ $tenant->user->email }}</dd>
                        <dt class="col-sm-5">Contact Number</dt>
                        <dd class="col-sm-7">{{ $tenant->user->contact_number ?? 'Not provided' }}</dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <h2 class="h5">Dormitory</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Room / Bed</dt>
                        <dd class="col-sm-7">
                            @if ($room && $bed)
                                Room {{ $room->room_number }} / {{ $bed->bed_label }}
                            @else
                                Not assigned
                            @endif
                        </dd>
                        <dt class="col-sm-5">Move-in Date</dt>
                        <dd class="col-sm-7">{{ $tenant->move_in_date?->format('M d, Y') }}</dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <h2 class="h5">Emergency Contact</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7">{{ $tenant->emergency_contact_name }}</dd>
                        <dt class="col-sm-5">Number</dt>
                        <dd class="col-sm-7">{{ $tenant->emergency_contact_number }}</dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <h2 class="h5">Guardian</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7">{{ $tenant->guardian_name ?? 'Not provided' }}</dd>
                        <dt class="col-sm-5">Number</dt>
                        <dd class="col-sm-7">{{ $tenant->guardian_contact_number ?? 'Not provided' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
