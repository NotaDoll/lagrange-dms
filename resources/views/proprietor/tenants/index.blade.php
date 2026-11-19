@extends('layouts.proprietor')

@section('title', 'Tenants')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Tenants</h1>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('proprietor.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Tenant</a>
            {{-- Less frequent actions live in the ⋮ menu, like on each tenant's page. --}}
            <div class="dropdown">
                <button class="btn btn-outline-secondary px-3" type="button" id="tenant-list-actions" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More tenant actions">
                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="tenant-list-actions">
                    <li><a class="dropdown-item" href="{{ route('proprietor.tenants.import.create') }}"><i class="bi bi-upload me-2" aria-hidden="true"></i>Bulk import</a></li>
                    <li><a class="dropdown-item" href="{{ route('proprietor.summer-storage.index') }}"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>Summer / storage</a></li>
                </ul>
            </div>
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
                    <input id="tenant-search" name="search" type="search" class="form-control" value="{{ $search }}" placeholder="Name, email, phone, or guardian">
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            @php
                                $assignment = $tenant->currentAssignment;
                                $bed = $assignment?->bed;
                                $room = $bed?->room;
                            @endphp
                            <tr class="tenant-row" data-href="{{ route('proprietor.tenants.show', $tenant) }}">
                                <td>
                                    <a href="{{ route('proprietor.tenants.show', $tenant) }}" class="fw-semibold text-reset text-decoration-none tenant-link">{{ $tenant->name }}</a>
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
                                <td>@if ($tenant->actualMoveInDate()){{ $tenant->actualMoveInDate()->format('M d, Y') }}@else<span class="text-muted">Not yet moved in</span>@endif</td>
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

    <style>
        .tenant-row { cursor: pointer; }
        .tenant-row:hover > td { background-color: var(--bs-tertiary-bg, #f8f9fa); }
        .tenant-link:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    </style>
    <script>
        // The whole row opens the tenant; the name stays a real link for keyboard and screen-reader users.
        document.querySelectorAll('.tenant-row').forEach((row) => row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, form')) return;
            if (window.getSelection()?.toString()) return; // let people select text
            if (event.ctrlKey || event.metaKey) { window.open(row.dataset.href, '_blank'); return; }
            window.location = row.dataset.href;
        }));
    </script>
@endsection
