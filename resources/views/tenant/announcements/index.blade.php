@extends('layouts.tenant')

@section('title', 'Announcements')

@section('content')
    <h1 class="mb-4">Announcements</h1>

    @forelse ($announcements as $announcement)
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">{{ $announcement->title }}</h5>
                <p class="text-muted small mb-2">{{ $announcement->created_at->format('M j, Y g:i A') }}</p>
                <p class="card-text">{{ $announcement->body }}</p>
            </div>
        </div>
    @empty
        <p class="text-muted">No announcements yet.</p>
    @endforelse

    {{ $announcements->links() }}
@endsection