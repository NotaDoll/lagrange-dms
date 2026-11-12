@extends('layouts.tenant')

@section('title', 'My Information')

@section('content')
    <h1 class="h3 mb-3">My Information</h1>

    @if (! $tenant)
        <div class="alert alert-info" role="status">We couldn’t find a tenant record linked to your account. Please contact the dormitory admin.</div>
    @else
        @php
            $bed = $tenant->currentAssignment?->bed;
            $room = $bed?->room;
            $phone = fn ($number) => \App\Rules\PhilippineMobile::normalize($number) ?? $number ?? 'Not provided';
        @endphp

        <p class="text-muted">To correct any information, please contact the dormitory admin.</p>

        <div class="card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h2 class="h5">Personal Information</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Full Name</dt>
                            <dd class="col-sm-7 text-break">{{ $tenant->name }}</dd>
                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7 text-break">{{ $tenant->email }}</dd>
                            <dt class="col-sm-5">Contact Number</dt>
                            <dd class="col-sm-7">{{ $phone($tenant->contact_number) }}</dd>
                            <dt class="col-sm-5">Home Address</dt>
                            <dd class="col-sm-7 text-break" style="white-space: pre-line">{{ $tenant->home_address ?? 'Not provided' }}</dd>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <h2 class="h5">Dormitory</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Room / Bed</dt>
                            <dd class="col-sm-7">
                                @if ($room && $bed)
                                    {{ $bed->locationLabel() }}
                                @else
                                    Not assigned
                                @endif
                            </dd>
                            <dt class="col-sm-5">Status</dt>
                            <dd class="col-sm-7">{{ \App\Models\Tenant::STATUS_LABELS[$tenant->status] ?? $tenant->status }}</dd>
                            <dt class="col-sm-5">Move-in Date</dt>
                            <dd class="col-sm-7">{{ $tenant->actualMoveInDate()?->format('M d, Y') ?? 'Not yet moved in' }}</dd>
                            <dt class="col-sm-5">Security Deposit</dt>
                            <dd class="col-sm-7">₱{{ number_format((float) $tenant->security_deposit, 2) }}</dd>
                            <dt class="col-sm-5">Advance Payment</dt>
                            <dd class="col-sm-7">₱{{ number_format((float) $tenant->advance_payment, 2) }}</dd>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <h2 class="h5">Emergency Contact</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Name</dt>
                            <dd class="col-sm-7 text-break">{{ $tenant->emergency_contact_name ?? 'Not provided' }}</dd>
                            <dt class="col-sm-5">Number</dt>
                            <dd class="col-sm-7">{{ $phone($tenant->emergency_contact_number) }}</dd>
                            <dt class="col-sm-5">Relationship</dt>
                            <dd class="col-sm-7">{{ \App\Models\Tenant::EMERGENCY_RELATIONSHIPS[$tenant->emergency_contact_relationship] ?? 'Not provided' }}</dd>
                        </dl>
                    </div>

                    @if ($tenant->monthModes->isNotEmpty())
                        <div class="col-md-6">
                            <h2 class="h5">Storage months</h2>
                            <ul class="mb-0">
                                @foreach ($tenant->monthModes as $mode)
                                    <li>{{ $mode->month->format('F Y') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($tenant->guardian_name || $tenant->guardian_contact_number)
                        <div class="col-md-6">
                            <h2 class="h5">Guardian</h2>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Name</dt>
                                <dd class="col-sm-7 text-break">{{ $tenant->guardian_name ?? 'Not provided' }}</dd>
                                <dt class="col-sm-5">Number</dt>
                                <dd class="col-sm-7">{{ $phone($tenant->guardian_contact_number) }}</dd>
                            </dl>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection
