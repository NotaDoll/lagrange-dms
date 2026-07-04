@extends(auth()->user()->isProprietor() ? 'layouts.proprietor' : 'layouts.tenant')

@section('title', 'Notifications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Notifications</h1>
        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Mark All Read</button>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    <div class="list-group-item {{ $notification->is_read ? '' : 'fw-semibold' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-uppercase small text-muted">{{ str_replace('_', ' ', $notification->type) }}</div>
                                <div>{{ $notification->message }}</div>
                            </div>
                            <div class="text-muted small">{{ $notification->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No notifications yet.</div>
                @endforelse
            </div>

            <div class="mt-3">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
@endsection
