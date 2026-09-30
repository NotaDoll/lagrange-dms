@extends('layouts.proprietor')

@section('title', 'Complaints')

@section('content')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .an {
            --an-line: var(--theme-border, #E8DEE5);
            --an-tint: var(--theme-pink-light, #FDF2F8);
            --an-plum: var(--theme-plum, #5E1049);
            --an-ink: var(--theme-text-dark, #111827);
            --an-ink-2: var(--theme-text-muted, #6b7280);
            --an-radius: 12px;
            --an-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 4px 12px -6px rgba(15, 23, 42, .08);

            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--an-ink);
        }
        .an button, .an input, .an select, .an .btn, .an .form-control, .an .form-select, .an .modal { font-family: inherit; }
        .an .btn { border-radius: 8px; font-weight: 500; font-size: .85rem; }
        .an .form-control, .an .form-select { border-color: var(--an-line); border-radius: 8px; font-size: .85rem; }
        .an-title { font-size: 1.6rem; font-weight: 600; margin: 0; }

        /* Filter + toolbar */
        .an-panel { background: #fff; border: 1px solid var(--an-line); border-radius: var(--an-radius); box-shadow: var(--an-shadow); }
        .an .form-label { font-size: .75rem; color: var(--an-ink-2); margin-bottom: .2rem; }
        .an-searchbox { position: relative; min-width: 15rem; }
        .an-searchbox .bi { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: var(--an-ink-2); pointer-events: none; }
        .an-searchbox input { padding-left: 2.1rem; }
        .an-chip { border: 1px solid var(--an-line); background: #fff; border-radius: 999px; padding: .3rem .75rem; font-size: .8rem; font-weight: 500; color: var(--an-ink-2); }
        .an-chip[aria-pressed="true"] { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
        .an-seg { display: inline-flex; padding: 3px; background: #f4eef2; border-radius: 10px; }
        .an-seg button { border: 0; background: transparent; border-radius: 8px; padding: .32rem .75rem; font-size: .8rem; font-weight: 500; color: var(--an-ink-2); }
        .an-seg button[aria-pressed="true"] { background: #fff; color: var(--an-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .an-count { font-size: .8rem; color: var(--an-ink-2); }

        /* Badges */
        .an-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 500; white-space: nowrap; background: #f1f5f9; color: #475569; }
        .an-badge.s-submitted { background: #e0e7ff; color: #4338ca; }
        .an-badge.s-in_progress { background: #fef3c7; color: #b45309; }
        .an-badge.s-resolved { background: #dcfce7; color: #15803d; }
        .an-badge.p-urgent { background: #fee2e2; color: #b91c1c; }
        .an-badge.p-aging { background: #ffedd5; color: #c2410c; }
        .an-badge.p-new { background: #dbeafe; color: #1d4ed8; }

        /* Compact table */
        .an-tablewrap { overflow: hidden; }
        .an-table { margin: 0; font-size: .8125rem; }
        .an-table thead th { background: var(--an-tint); color: var(--an-ink-2); font-size: .72rem; font-weight: 600; text-transform: none; border-bottom: 1px solid var(--an-line); padding: .55rem .75rem; white-space: nowrap; }
        .an-table td { padding: .45rem .75rem; border-color: var(--an-line); vertical-align: middle; }
        .an-table .t-name { font-weight: 600; line-height: 1.25; }
        .an-table .t-sub { color: var(--an-ink-2); font-size: .72rem; line-height: 1.25; }
        .an-table .t-desc { max-width: 24rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #374151; }
        .an .complaint-row { cursor: pointer; }
        .an .complaint-row td { transition: background-color .15s ease; }
        .an .complaint-row:hover td { background: var(--an-tint); }
        .an .complaint-row.is-urgent td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
        .an .complaint-row.is-aging td:first-child { box-shadow: inset 3px 0 0 #ea580c; }
        .an-status { display: flex; gap: .35rem; align-items: center; min-width: 11rem; }
        .an-status .form-select { padding-top: .2rem; padding-bottom: .2rem; font-size: .78rem; }
        .an-status .btn { padding: .2rem .5rem; font-size: .78rem; line-height: 1.4; }

        /* Card feed */
        .an .complaint-card { background: #fff; border: 1px solid var(--an-line); border-radius: var(--an-radius); box-shadow: var(--an-shadow); padding: .9rem 1rem; height: 100%; cursor: pointer; transition: border-color .15s, box-shadow .15s; display: flex; flex-direction: column; gap: .5rem; }
        .an .complaint-card:hover { border-color: #e2b6d2; box-shadow: 0 10px 24px -12px rgba(94, 16, 73, .25); }
        .an .complaint-card.is-urgent { border-left: 3px solid #dc2626; }
        .an .complaint-card.is-aging { border-left: 3px solid #ea580c; }
        .an .complaint-card p { margin: 0; font-size: .82rem; color: #374151; line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .an-empty { text-align: center; color: var(--an-ink-2); padding: 3rem 1rem; }
        .an-empty .bi { display: block; font-size: 1.75rem; margin-bottom: .5rem; }
        .an a:focus-visible, .an button:focus-visible, .an .complaint-row:focus-visible, .an .complaint-card:focus-visible { outline: 2px solid var(--an-plum); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { .an * { transition: none !important; } }

        /* Detail modal (kept from the original, now scoped) */
        .an .complaint-detail-modal .modal-content { border: none; border-radius: 16px; box-shadow: 0 18px 50px rgba(31, 31, 31, .16); }
        .an .complaint-detail-modal .modal-header { border-bottom: 1px solid var(--an-line); padding: 20px 24px; }
        .an .complaint-detail-modal .modal-title { color: var(--an-plum); font-weight: 700; }
        .an .complaint-detail-modal .modal-body { padding: 24px; }
        .an .complaint-detail-label { color: var(--an-ink-2); font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .an .complaint-detail-value { color: var(--an-ink); font-weight: 600; }
        .an .complaint-detail-description { background: var(--an-tint); border: 1px solid var(--an-line); border-radius: 12px; color: #374151; line-height: 1.6; padding: 16px; white-space: pre-wrap; }
    </style>

    @php
        $catIcons = ['maintenance' => 'bi-tools', 'noise' => 'bi-volume-up', 'billing' => 'bi-receipt', 'other' => 'bi-chat-dots'];
        $statusLabels = ['submitted' => 'Submitted', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'];

        // Priority is derived from what the complaint already has:
        // an urgency/priority column (if you have one), then how long it has waited unresolved.
        $meta = function ($c) use ($catIcons) {
            $attrs = $c->getAttributes();
            $flag = Str::lower((string) ($attrs['priority'] ?? $attrs['urgency'] ?? ''));
            $days = (int) $c->created_at->diffInDays(now());
            $open = $c->status !== 'resolved';

            if ($open && in_array($flag, ['urgent', 'high'], true)) { $p = ['urgent', 'Urgent', 'bi-exclamation-triangle-fill']; }
            elseif ($open && $days >= 7) { $p = ['aging', 'Waiting ' . $days . 'd', 'bi-hourglass-split']; }
            elseif ($open && $days < 2) { $p = ['new', 'New', 'bi-stars']; }
            else { $p = [null, null, null]; }

            return (object) [
                'pKey' => $p[0], 'pLabel' => $p[1], 'pIcon' => $p[2],
                'attention' => in_array($p[0], ['urgent', 'aging'], true),
                'catIcon' => $catIcons[$c->category] ?? 'bi-chat-dots',
                'search' => Str::lower($c->tenant->user->name . ' ' . $c->tenant->user->email . ' ' . $c->category . ' ' . $c->description),
            ];
        };
    @endphp

    <div class="an">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="an-title">Complaints</h1>
            <a href="{{ route('proprietor.complaints.summarize') }}" class="btn btn-primary"><i class="bi bi-magic me-1"></i>Summarize</a>
        </div>

        {{-- Server-side filters (unchanged behavior) --}}
        <div class="an-panel mb-3">
            <div class="p-3">
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
                            @foreach ($statusLabels as $value => $label)
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

        @if ($complaints->isEmpty())
            <div class="an-panel"><div class="an-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No complaints found.</div></div>
        @else
            {{-- Quick search, attention filter and view switch --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="an-searchbox">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="anSearch" class="form-control" placeholder="Search tenant, category or text" aria-label="Search complaints on this page">
                </div>
                <button type="button" class="an-chip" id="anAttention" aria-pressed="false"><i class="bi bi-exclamation-triangle me-1"></i>Needs attention</button>
                <div class="an-count ms-1" id="anCount" aria-live="polite">Showing {{ $complaints->count() }} of {{ $complaints->count() }}</div>
                <div class="an-seg ms-auto" role="group" aria-label="Switch view">
                    <button type="button" data-view="table" aria-pressed="true"><i class="bi bi-table me-1"></i>Table</button>
                    <button type="button" data-view="cards" aria-pressed="false"><i class="bi bi-grid me-1"></i>Cards</button>
                </div>
            </div>

            {{-- Table --}}
            <div id="anTable" class="an-panel an-tablewrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle an-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($complaints as $complaint)
                                @php $m = $meta($complaint); @endphp
                                <tr class="complaint-row an-item {{ $m->pKey ? 'is-' . $m->pKey : '' }}"
                                    data-complaint-modal="#complaintModal{{ $complaint->id }}"
                                    data-search="{{ $m->search }}" data-attention="{{ $m->attention ? 1 : 0 }}"
                                    tabindex="0" aria-label="View complaint from {{ $complaint->tenant->user->name }}">
                                    <td>
                                        <div class="t-name">{{ $complaint->tenant->user->name }}</div>
                                        <div class="t-sub">{{ $complaint->tenant->user->email }}</div>
                                    </td>
                                    <td><span class="an-badge"><i class="bi {{ $m->catIcon }}"></i>{{ ucfirst($complaint->category) }}</span></td>
                                    <td><div class="t-desc">{{ Str::limit($complaint->description, 80) }}</div></td>
                                    <td>
                                        @if ($m->pKey)
                                            <span class="an-badge p-{{ $m->pKey }}"><i class="bi {{ $m->pIcon }}"></i>{{ $m->pLabel }}</span>
                                        @else
                                            <span class="t-sub">Normal</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], request()->only(['category', 'status']))) }}" class="an-status complaint-status-form">
                                            @csrf
                                            @method('PATCH')
                                            <select class="form-select form-select-sm" name="status" aria-label="Complaint status">
                                                @foreach ($statusLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($complaint->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Update status" aria-label="Update status"><i class="bi bi-check2"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $complaint->created_at->format('M d, Y') }}
                                        <div class="t-sub">{{ $complaint->created_at->diffForHumans() }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cards --}}
            <div id="anCards" class="row g-2 row-cols-1 row-cols-md-2 row-cols-xl-3" hidden>
                @foreach ($complaints as $complaint)
                    @php $m = $meta($complaint); @endphp
                    <div class="col an-item" data-search="{{ $m->search }}" data-attention="{{ $m->attention ? 1 : 0 }}">
                        <article class="complaint-card {{ $m->pKey ? 'is-' . $m->pKey : '' }}" data-complaint-modal="#complaintModal{{ $complaint->id }}" tabindex="0" aria-label="View complaint from {{ $complaint->tenant->user->name }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold" style="font-size:.9rem">{{ $complaint->tenant->user->name }}</div>
                                    <div class="text-muted" style="font-size:.72rem">{{ $complaint->created_at->format('M d, Y') }} &middot; {{ $complaint->created_at->diffForHumans() }}</div>
                                </div>
                                @if ($m->pKey)
                                    <span class="an-badge p-{{ $m->pKey }}"><i class="bi {{ $m->pIcon }}"></i>{{ $m->pLabel }}</span>
                                @endif
                            </div>
                            <div class="d-flex gap-1 flex-wrap">
                                <span class="an-badge"><i class="bi {{ $m->catIcon }}"></i>{{ ucfirst($complaint->category) }}</span>
                                <span class="an-badge s-{{ $complaint->status }}">{{ $statusLabels[$complaint->status] ?? Str::of($complaint->status)->replace('_', ' ')->title() }}</span>
                            </div>
                            <p>{{ $complaint->description }}</p>
                            <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], request()->only(['category', 'status']))) }}" class="an-status complaint-status-form mt-auto">
                                @csrf
                                @method('PATCH')
                                <select class="form-select form-select-sm" name="status" aria-label="Complaint status">
                                    @foreach ($statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($complaint->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary btn-sm" title="Update status" aria-label="Update status"><i class="bi bi-check2"></i></button>
                            </form>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="an-panel an-empty mt-2" id="anNoMatch" hidden><i class="bi bi-search" aria-hidden="true"></i>No complaints on this page match your search.</div>

            <div class="mt-3">
                {{ $complaints->links() }}
            </div>
        @endif

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
    </div>

    @push('scripts')
        <script>
            (function () {
                var openers = document.querySelectorAll('.an [data-complaint-modal]');

                function openModal(el) {
                    var modalElement = document.querySelector(el.dataset.complaintModal);
                    if (modalElement) { bootstrap.Modal.getOrCreateInstance(modalElement).show(); }
                }

                openers.forEach(function (el) {
                    el.addEventListener('click', function (event) {
                        if (event.target.closest('form, button, select, input, a')) { return; }
                        openModal(el);
                    });
                    el.addEventListener('keydown', function (event) {
                        if (event.key !== 'Enter' && event.key !== ' ') { return; }
                        if (event.target !== el) { return; }
                        event.preventDefault();
                        openModal(el);
                    });
                });

                // Search, attention filter and view switch (client-side, current page only)
                var table = document.getElementById('anTable');
                if (!table) { return; }
                var cards = document.getElementById('anCards');
                var search = document.getElementById('anSearch');
                var attn = document.getElementById('anAttention');
                var noMatch = document.getElementById('anNoMatch');
                var count = document.getElementById('anCount');
                var rows = table.querySelectorAll('.an-item');
                var total = rows.length;
                var view = 'table';

                function apply() {
                    var q = search.value.trim().toLowerCase();
                    var onlyAttn = attn.getAttribute('aria-pressed') === 'true';
                    var shown = 0;
                    document.querySelectorAll('.an .an-item').forEach(function (el) {
                        var ok = (!q || el.dataset.search.indexOf(q) > -1) && (!onlyAttn || el.dataset.attention === '1');
                        el.hidden = !ok;
                        if (ok && table.contains(el)) { shown++; }
                    });
                    count.textContent = 'Showing ' + shown + ' of ' + total;
                    noMatch.hidden = shown !== 0;
                }

                function setView(v) {
                    view = v;
                    table.hidden = v !== 'table';
                    cards.hidden = v !== 'cards';
                    document.querySelectorAll('.an [data-view]').forEach(function (b) {
                        b.setAttribute('aria-pressed', b.dataset.view === v ? 'true' : 'false');
                    });
                    try { localStorage.setItem('an-complaints-view', v); } catch (e) {}
                }

                search.addEventListener('input', apply);
                attn.addEventListener('click', function () {
                    attn.setAttribute('aria-pressed', attn.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                    apply();
                });
                document.querySelectorAll('.an [data-view]').forEach(function (b) {
                    b.addEventListener('click', function () { setView(b.dataset.view); });
                });

                var saved = null;
                try { saved = localStorage.getItem('an-complaints-view'); } catch (e) {}
                setView(saved === 'cards' ? 'cards' : 'table');
            })();
        </script>
    @endpush
@endsection
