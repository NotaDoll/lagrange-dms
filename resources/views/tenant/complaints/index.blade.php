@extends('layouts.tenant')

@section('title', 'My Complaints')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">My Complaints</h1>
        <a href="{{ route('tenant.complaints.create') }}" class="btn btn-primary">Submit Complaint</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($complaints as $complaint)
                            <tr>
                                <td>{{ ucfirst($complaint->category) }}</td>
                                <td>{{ Str::limit($complaint->description, 90) }}</td>
                                <td>
                                    <span class="badge text-bg-{{ ['submitted' => 'secondary', 'in_progress' => 'warning', 'resolved' => 'success'][$complaint->status] }}">
                                        {{ Str::headline($complaint->status) }}
                                    </span>
                                </td>
                                <td>{{ $complaint->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No complaints submitted.</td>
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
@endsection
