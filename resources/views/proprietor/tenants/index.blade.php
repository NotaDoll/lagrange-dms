@extends('layouts.proprietor')

@section('title', 'Tenants')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Tenants</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('proprietor.tenants.create') }}" class="btn btn-primary">Add Tenant</a>
            <a href="{{ route('proprietor.tenants.import.create') }}" class="btn btn-outline-primary">Bulk Import</a>
            <a href="{{ route('proprietor.summer-storage.index') }}" class="btn btn-outline-primary">Summer / Storage</a>
        </div>
    </div>

    @if (session('notice'))
        <div class="alert alert-info" role="status">{{ session('notice') }}</div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">All tenants</div><div class="fs-4 fw-semibold">{{ $tenantCounts['all'] }}</div></div></div></div>
        <div class="col-6 col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Active</div><div class="fs-4 fw-semibold text-success">{{ $tenantCounts['active'] }}</div></div></div></div>
        <div class="col-6 col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Inactive</div><div class="fs-4 fw-semibold">{{ $tenantCounts['inactive'] }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.tenants.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-7">
                    <label for="tenant-search" class="form-label">Search tenants</label>
                    <input id="tenant-search" name="search" type="search" class="form-control" value="{{ $search }}" placeholder="Name, email, phone, or emergency contact">
                </div>
                <div class="col-8 col-md-3">
                    <label for="tenant-status" class="form-label">Status</label>
                    <select id="tenant-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Tenant::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1">Filter</button>
                    <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Current Room/Bed</th>
                            <th>Status</th>
                            <th>Move-in Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            @php
                                $assignment = $tenant->currentAssignment;
                                $bed = $assignment?->bed;
                                $room = $bed?->room;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $tenant->name }}</div>
                                    <div class="text-muted small">{{ $tenant->email }}</div>
                                    @if (! $tenant->user_id)
                                        <div class="text-muted small">No login account</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($room && $bed)
                                        {{ $bed->locationLabel() }}
                                    @else
                                        <span class="text-muted">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $tenant->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ \App\Models\Tenant::STATUS_LABELS[$tenant->status] ?? $tenant->status }}
                                    </span>
                                </td>
                                <td>{{ $tenant->actualMoveInDate()?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group" role="group" aria-label="Tenant actions">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#tenantModal{{ $tenant->id }}">
                                            View
                                        </button>
                                        <a href="{{ route('proprietor.tenants.edit', $tenant) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('proprietor.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this tenant and their login account?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No tenants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $tenants->links() }}
            </div>
        </div>
    </div>

    @foreach ($tenants as $tenant)
        <div class="modal fade" id="tenantModal{{ $tenant->id }}" tabindex="-1" aria-labelledby="tenantModalLabel{{ $tenant->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="tenantModalLabel{{ $tenant->id }}">{{ $tenant->name }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7">{{ $tenant->email }}</dd>
                            <dt class="col-sm-5">Contact Number</dt>
                            <dd class="col-sm-7">{{ \App\Rules\PhilippineMobile::normalize($tenant->contact_number) ?? $tenant->contact_number ?? 'Not provided' }}</dd>
                            <dt class="col-sm-5">Home Address</dt>
                            <dd class="col-sm-7 text-break" style="white-space: pre-line">{{ $tenant->home_address ?? 'Not provided' }}</dd>
                            <dt class="col-sm-5">Emergency Contact</dt>
                            <dd class="col-sm-7">
                                {{ $tenant->emergency_contact_name ?? 'Not provided' }}
                                @if ($tenant->emergency_contact_number)
                                    ({{ \App\Rules\PhilippineMobile::normalize($tenant->emergency_contact_number) ?? $tenant->emergency_contact_number }})
                                @endif
                            </dd>
                            <dt class="col-sm-5">Relationship</dt>
                            <dd class="col-sm-7">{{ \App\Models\Tenant::EMERGENCY_RELATIONSHIPS[$tenant->emergency_contact_relationship] ?? 'Not provided' }}</dd>
                            <dt class="col-sm-5">Guardian</dt>
                            <dd class="col-sm-7">
                                @if ($tenant->guardian_name || $tenant->guardian_contact_number)
                                    {{ $tenant->guardian_name ?? 'Not provided' }}{{ $tenant->guardian_contact_number ? ' (' . (\App\Rules\PhilippineMobile::normalize($tenant->guardian_contact_number) ?? $tenant->guardian_contact_number) . ')' : '' }}
                                @else
                                    Not provided
                                @endif
                            </dd>
                            <dt class="col-sm-5">Move-in Date</dt>
                            <dd class="col-sm-7">{{ $tenant->actualMoveInDate()?->format('M d, Y') ?? 'Not yet moved in' }}</dd>
                            <dt class="col-sm-5">Room / Bed</dt>
                            <dd class="col-sm-7">
                                @if ($tenant->currentAssignment?->bed?->room)
                                    {{ $tenant->currentAssignment->bed->locationLabel() }}
                                @else
                                    Unassigned
                                @endif
                            </dd>
                            <dt class="col-sm-5">Status</dt>
                            <dd class="col-sm-7">{{ \App\Models\Tenant::STATUS_LABELS[$tenant->status] ?? $tenant->status }}</dd>
                            <dt class="col-sm-5">Security Deposit</dt>
                            <dd class="col-sm-7">₱{{ number_format((float) $tenant->security_deposit, 2) }}</dd>
                            <dt class="col-sm-5">Advance Payment</dt>
                            <dd class="col-sm-7">₱{{ number_format((float) $tenant->advance_payment, 2) }}</dd>
                            <dt class="col-sm-5">Data Privacy Consent</dt>
                            <dd class="col-sm-7">
                                @if ($tenant->data_privacy_consent_at)
                                    {{ $tenant->data_privacy_consent_at->copy()->timezone(\App\Models\Tenant::LOCAL_TIMEZONE)->format('M d, Y, g:i A') }}
                                    @if ($tenant->consentRecorder)
                                        <div class="small text-muted">Recorded by {{ $tenant->consentRecorder->name }}</div>
                                    @endif
                                @else
                                    Not recorded
                                @endif
                            </dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        @if ($tenant->hasMovedIn())
                            <a href="{{ route('proprietor.payments.create', ['tenant_id' => $tenant->id]) }}" class="btn btn-primary">Record Payment</a>
                        @else
                            <span class="small text-muted">Payment recording opens after move-in.</span>
                            <button type="button" class="btn btn-primary" disabled>Record Payment</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
