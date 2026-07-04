@extends('layouts.proprietor')

@section('title', 'Tenants')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Tenants</h1>
        <a href="{{ route('proprietor.tenants.create') }}" class="btn btn-primary">Add Tenant</a>
        <a href="{{ route('proprietor.tenants.import.create') }}" class="btn btn-outline-primary">Bulk Import</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('temp_password'))
        <div class="alert alert-warning">
            <strong>Temporary password for {{ session('temp_password_email') }}:</strong>
            <code>{{ session('temp_password') }}</code>
            <div class="small mt-1">Copy this now — it will not be shown again.</div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Current Room/Bed</th>
                            <th>Status</th>
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
                                    <div class="fw-semibold">{{ $tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $tenant->user->email }}</div>
                                </td>
                                <td>
                                    @if ($room && $bed)
                                        Room {{ $room->room_number }} / {{ $bed->bed_label }}
                                    @else
                                        <span class="text-muted">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $tenant->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ ucfirst($tenant->status) }}
                                    </span>
                                </td>
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
                                <td colspan="4" class="text-center text-muted py-4">No tenants found.</td>
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
                        <h2 class="modal-title h5" id="tenantModalLabel{{ $tenant->id }}">{{ $tenant->user->name }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7">{{ $tenant->user->email }}</dd>
                            <dt class="col-sm-5">Contact Number</dt>
                            <dd class="col-sm-7">{{ $tenant->user->contact_number ?? 'Not provided' }}</dd>
                            <dt class="col-sm-5">Emergency Contact</dt>
                            <dd class="col-sm-7">{{ $tenant->emergency_contact_name }} ({{ $tenant->emergency_contact_number }})</dd>
                            <dt class="col-sm-5">Guardian</dt>
                            <dd class="col-sm-7">
                                @if ($tenant->guardian_name || $tenant->guardian_contact_number)
                                    {{ $tenant->guardian_name ?? 'Not provided' }}{{ $tenant->guardian_contact_number ? ' (' . $tenant->guardian_contact_number . ')' : '' }}
                                @else
                                    Not provided
                                @endif
                            </dd>
                            <dt class="col-sm-5">Move-in Date</dt>
                            <dd class="col-sm-7">{{ $tenant->move_in_date?->format('M d, Y') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection