@extends(auth()->user()->isProprietor() ? 'layouts.proprietor' : 'layouts.tenant')

@section('title', 'Notifications')

@section('content')
    <style>
        .notif-item { cursor: pointer; text-align: left; width: 100%; }
        .notif-item.is-unread { background: #fdf2f8; }
        .notif-item.is-unread .notif-dot { display: inline-block; }
        .notif-dot { display: none; width: 8px; height: 8px; border-radius: 50%; background: #c0249f; margin-right: 6px; vertical-align: middle; }
        .notif-preview { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        #notifModalMessage { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.6; }
    </style>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Notifications</h1>
        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check2-all" aria-hidden="true"></i> Mark All Read</button>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="small text-muted mb-2">Tap a notification to read it in full. Opening it marks it as read.</p>
            <div class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    {{-- Each notification opens in the modal below; opening it marks it read. --}}
                    <button type="button" class="list-group-item list-group-item-action notif-item {{ $notification->is_read ? '' : 'is-unread fw-semibold' }}"
                            data-notif-title="{{ Str::headline($notification->type) }}"
                            data-notif-date="{{ $notification->created_at->format('M d, Y h:i A') }}"
                            data-notif-message="{{ $notification->message }}"
                            data-notif-url="{{ $notification->url() }}"
                            data-notif-link="{{ $notification->linkLabel() }}"
                            data-notif-read-url="{{ route('notifications.read', $notification) }}"
                            data-notif-read="{{ $notification->is_read ? 1 : 0 }}">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="text-uppercase small text-muted"><span class="notif-dot" aria-hidden="true"></span>{{ str_replace('_', ' ', $notification->type) }}@unless ($notification->is_read)<span class="visually-hidden"> (unread)</span>@endunless</div>
                                <div class="notif-preview">{{ $notification->message }}</div>
                            </div>
                            <div class="text-muted small text-nowrap">{{ $notification->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                    </button>
                @empty
                    <div class="list-group-item text-muted">No notifications yet.</div>
                @endforelse
            </div>

            <div class="mt-3">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>

    <div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: min(520px, calc(100vw - 1.5rem));">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 mb-0" id="notifModalTitle"></h2>
                        <div class="small text-muted" id="notifModalDate"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"><p class="mb-0" id="notifModalMessage"></p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <a class="btn btn-primary" id="notifModalLink" hidden></a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const modalEl = document.getElementById('notifModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const link = document.getElementById('notifModalLink');

    document.querySelectorAll('.notif-item').forEach((item) => item.addEventListener('click', async () => {
        const data = item.dataset;
        document.getElementById('notifModalTitle').textContent = data.notifTitle;
        document.getElementById('notifModalDate').textContent = data.notifDate;
        document.getElementById('notifModalMessage').textContent = data.notifMessage;
        link.hidden = !data.notifUrl;
        if (data.notifUrl) {
            link.href = data.notifUrl;
            link.textContent = data.notifLink || 'Open';
        }
        modal.show();

        if (data.notifRead === '1') return;
        try {
            const response = await fetch(data.notifReadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
            if (!response.ok) return;
            const result = await response.json();
            item.dataset.notifRead = '1';
            item.classList.remove('is-unread', 'fw-semibold');
            item.querySelector('.visually-hidden')?.remove();
            // Keep the bell's unread badge in step with what is left unread.
            document.querySelectorAll('.tenant-navbar .notif-badge, .tenant-mobile-sidebar .notif-badge').forEach((badge) => {
                badge.textContent = result.unread;
                badge.style.display = result.unread > 0 ? 'inline-block' : 'none';
            });
        } catch (error) {
            console.error('Could not mark the notification as read.', error);
        }
    }));
})();
</script>
@endpush
