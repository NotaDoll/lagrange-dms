@extends('layouts.proprietor')

@section('title', 'Announcements')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Announcements</h1>
        <a href="{{ route('proprietor.announcements.create') }}" class="btn btn-primary">New Announcement</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Title</th>
                <th>Posted</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($announcements as $announcement)
                <tr>
                    <td>{{ $announcement->title }}</td>
                    <td>{{ $announcement->created_at->format('M j, Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('proprietor.announcements.edit', $announcement) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <form action="{{ route('proprietor.announcements.destroy', $announcement) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this announcement?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted">No announcements yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $announcements->links() }}
@endsection