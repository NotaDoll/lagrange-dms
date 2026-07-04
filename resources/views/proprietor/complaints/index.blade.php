@extends('layouts.proprietor')

@section('title', 'Complaints')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Complaints</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('proprietor.complaints.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['submitted' => 'Submitted', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('proprietor.complaints.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th>Tenant</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($complaints as $complaint)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $complaint->tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $complaint->tenant->user->email }}</div>
                                </td>
                                <td>{{ ucfirst($complaint->category) }}</td>
                                <td>
                                    {{ Str::limit($complaint->description, 80) }}
                                    @if (Str::length($complaint->description) > 80)
                                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#complaintModal{{ $complaint->id }}">
                                            View more
                                        </button>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], request()->only(['category', 'status']))) }}" class="d-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select class="form-select form-select-sm" name="status" aria-label="Complaint status">
                                            @foreach (['submitted' => 'Submitted', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'] as $value => $label)
                                                <option value="{{ $value }}" @selected($complaint->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Update</button>
                                    </form>
                                </td>
                                <td>{{ $complaint->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No complaints found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $complaints->links() }}
            </div>
        </div>
    </div>

    @foreach ($complaints as $complaint)
        <div class="modal fade" id="complaintModal{{ $complaint->id }}" tabindex="-1" aria-labelledby="complaintModalLabel{{ $complaint->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="complaintModalLabel{{ $complaint->id }}">{{ ucfirst($complaint->category) }} Complaint</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2 fw-semibold">{{ $complaint->tenant->user->name }}</div>
                        <p class="mb-0">{{ $complaint->description }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
