@extends('layouts.proprietor')

@section('title', 'Complaints')

@section('content')
    <style>
        .complaint-row {
            cursor: pointer;
        }

        .complaint-row:hover td {
            background: var(--theme-pink-light, #FDF2F8);
        }

        .complaint-row td {
            transition: background-color 0.2s ease;
        }

        .complaint-detail-modal .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 18px 50px rgba(31, 31, 31, 0.16);
        }

        .complaint-detail-modal .modal-header {
            border-bottom: 1px solid var(--theme-border, #E8DEE5);
            padding: 20px 24px;
        }

        .complaint-detail-modal .modal-title {
            color: var(--theme-plum, #5E1049);
            font-weight: 700;
        }

        .complaint-detail-modal .modal-body {
            padding: 24px;
        }

        .complaint-detail-label {
            color: var(--theme-text-muted, #6b7280);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .complaint-detail-value {
            color: var(--theme-text-dark, #111827);
            font-weight: 600;
        }

        .complaint-detail-description {
            background: var(--theme-pink-light, #FDF2F8);
            border: 1px solid var(--theme-border, #E8DEE5);
            border-radius: 12px;
            color: #374151;
            line-height: 1.6;
            padding: 16px;
            white-space: pre-wrap;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Complaints</h1>
        <a href="{{ route('proprietor.complaints.summarize') }}" class="btn btn-primary">Summarize</a>
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
                            <tr class="complaint-row" data-complaint-modal="#complaintModal{{ $complaint->id }}" tabindex="0" aria-label="View complaint from {{ $complaint->tenant->user->name }}">
                                <td>
                                    <div class="fw-semibold">{{ $complaint->tenant->user->name }}</div>
                                    <div class="text-muted small">{{ $complaint->tenant->user->email }}</div>
                                </td>
                                <td>{{ ucfirst($complaint->category) }}</td>
                                <td>
                                    {{ Str::limit($complaint->description, 80) }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], request()->only(['category', 'status']))) }}" class="d-flex gap-2 complaint-status-form">
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
        <div class="modal fade complaint-detail-modal" id="complaintModal{{ $complaint->id }}" tabindex="-1" aria-labelledby="complaintModalLabel{{ $complaint->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="complaintModalLabel{{ $complaint->id }}">{{ ucfirst($complaint->category) }} Complaint</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="complaint-detail-label">Tenant</div>
                                <div class="complaint-detail-value">{{ $complaint->tenant->user->name }}</div>
                                <div class="text-muted small">{{ $complaint->tenant->user->email }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="complaint-detail-label">Status</div>
                                <div class="complaint-detail-value">{{ Str::of($complaint->status)->replace('_', ' ')->title() }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="complaint-detail-label">Submitted</div>
                                <div class="complaint-detail-value">{{ $complaint->created_at->format('M d, Y') }}</div>
                            </div>
                        </div>

                        <div class="complaint-detail-label mb-2">Description</div>
                        <div class="complaint-detail-description">{{ $complaint->description }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @push('scripts')
        <script>
            document.querySelectorAll('.complaint-row').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('form, button, select, input, a')) {
                        return;
                    }

                    const modalElement = document.querySelector(row.dataset.complaintModal);
                    if (modalElement) {
                        bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                });

                row.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    const modalElement = document.querySelector(row.dataset.complaintModal);
                    if (modalElement) {
                        bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                });
            });
        </script>
    @endpush
@endsection
