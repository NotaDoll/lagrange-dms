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
            --an-radius: var(--theme-radius);
            --an-shadow: var(--theme-shadow);

            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--an-ink);
        }
        .an button, .an input, .an select, .an .btn, .an .form-control, .an .form-select, .an .modal { font-family: inherit; }
        .an .form-control, .an .form-select { font-size: .85rem; }
        .an-title { font-size: 1.6rem; font-weight: 600; margin: 0; }

        /* Filter + toolbar */
        .an-panel { background: #fff; border: 1px solid var(--an-line); border-radius: var(--an-radius); box-shadow: var(--an-shadow); }
        .an .form-label { font-size: .75rem; color: var(--an-ink-2); margin-bottom: .2rem; }
        .an-filter { flex: 1 1 9rem; min-width: 0; }
        .an-filter-search { flex: 2 1 14rem; }
        .an-searchbox { position: relative; }
        .an-searchbox > .bi { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: var(--an-ink-2); pointer-events: none; }
        .an-searchbox input { padding-inline: 2.1rem; }
        .an-searchbox input::-webkit-search-cancel-button { display: none; }
        .an-searchbox .an-clear-search { position: absolute; right: .25rem; top: 50%; transform: translateY(-50%); padding: .15rem .35rem; color: var(--an-ink-2); }
        .an-selection { font-size: .8rem; }
        .an .form-check-input:checked, .an .form-check-input:indeterminate { background-color: var(--an-plum); border-color: var(--an-plum); }
        .an-chip { border: 1px solid var(--an-line); background: #fff; border-radius: 999px; padding: .3rem .75rem; font-size: .8rem; font-weight: 500; color: var(--an-ink-2); }
        .an-chip[aria-pressed="true"] { background: var(--theme-danger-bg); border-color: #fecaca; color: var(--theme-danger); }
        .an-seg { display: inline-flex; padding: 3px; background: var(--theme-pink-light); border-radius: var(--theme-btn-radius); }
        .an-seg button { border: 0; background: transparent; border-radius: calc(var(--theme-btn-radius) - 3px); padding: .32rem .75rem; font-size: .8rem; font-weight: 500; color: var(--an-ink-2); }
        .an-seg button[aria-pressed="true"] { background: #fff; color: var(--an-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .an-count { font-size: .8rem; color: var(--an-ink-2); }
        .an-pagination .pagination { margin-bottom: 0; }
        .an-pagination nav > .d-sm-flex > .small.text-muted { display: none; }

        /* Badges */
        .an-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 500; white-space: nowrap; background: #f1f5f9; color: #475569; }
        .an-badge.s-in_progress { background: #fef3c7; color: #b45309; }
        .an-badge.s-resolved { background: #dcfce7; color: #15803d; }
        .an-badge.p-urgent { background: #fee2e2; color: #b91c1c; }
        .an-badge.p-aging { background: #ffedd5; color: #c2410c; }
        .an-badge.p-new { background: var(--theme-info-bg); color: var(--theme-info); }

        /* Compact table */
        .an-tablewrap { overflow: hidden; }
        .an-table { margin: 0; font-size: .8125rem; }
        .an-table thead th { background: var(--an-tint); color: var(--an-ink-2); font-size: .72rem; font-weight: 600; text-transform: none; border-bottom: 1px solid var(--an-line); padding: .55rem .75rem; white-space: nowrap; }
        .an-table td { padding: .45rem .75rem; border-color: var(--an-line); vertical-align: middle; }
        .an-table .t-name { line-height: 1.25; }
        .an .is-unread .t-name { font-weight: 700; }
        .an .complaint-row.is-unread > td,
        .an .complaint-card.is-unread { background: #f3f4f6; }
        .an .complaint-row.is-unread:hover > td { --bs-table-bg-state: #f3f4f6; }
        .an-table .t-sub { color: var(--an-ink-2); font-size: .72rem; line-height: 1.25; }
        .an-table .t-desc { max-width: 24rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #374151; }
        .an .complaint-row { cursor: pointer; }
        .an .complaint-row td { transition: background-color .15s ease; }
        .an .complaint-row:hover td { background: var(--an-tint); }
        .an .complaint-row.is-urgent td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
        .an .complaint-row.is-aging td:first-child { box-shadow: inset 3px 0 0 #ea580c; }
        .an-status { display: flex; gap: .35rem; align-items: center; min-width: 11rem; }
        .an-status .form-select { padding-top: .2rem; padding-bottom: .2rem; font-size: .78rem; }
        .an-status .btn { --bs-btn-padding-x: .65rem; --bs-btn-padding-y: .15rem; --bs-btn-font-size: .78rem; min-height: 30px; }

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
        $statusLabels = \App\Models\Complaint::STATUS_LABELS;
        $filterQuery = request()->only(['search', 'category', 'date_from', 'date_to', 'status', 'page']);
        $displayStatus = fn ($complaint) => isset($statusLabels[$complaint->status]) ? $complaint->status : \App\Models\Complaint::STATUS_IN_PROGRESS;

        // Priority is derived from what the complaint already has:
        // an urgency/priority column (if you have one), then how long it has waited unresolved.
        $meta = function ($c) use ($catIcons) {
            $attrs = $c->getAttributes();
            $flag = Str::lower((string) ($attrs['priority'] ?? $attrs['urgency'] ?? ''));
            $days = (int) $c->created_at->diffInDays(now());
            $open = $c->status !== \App\Models\Complaint::STATUS_RESOLVED;

            if ($open && in_array($flag, ['urgent', 'high'], true)) { $p = ['urgent', 'Urgent', 'bi-exclamation-triangle-fill']; }
            elseif ($open && $days >= 7) { $p = ['aging', 'Waiting ' . $days . 'd', 'bi-hourglass-split']; }
            elseif ($open && $days < 2) { $p = ['new', 'New', 'bi-stars']; }
            else { $p = [null, null, null]; }

            return (object) [
                'pKey' => $p[0], 'pLabel' => $p[1], 'pIcon' => $p[2],
                'attention' => in_array($p[0], ['urgent', 'aging'], true),
                'catIcon' => $catIcons[$c->category] ?? 'bi-chat-dots',
            ];
        };
    @endphp

    <div class="an">
        <h1 class="an-title mb-3">Complaints</h1>

        <div class="an-panel p-3 mb-3">
            <form method="GET" action="{{ route('proprietor.complaints.index') }}" id="anFilters" class="d-flex flex-wrap gap-3 align-items-end">
                <div class="an-filter an-filter-search">
                    <label for="anSearch" class="form-label">Search</label>
                    <div class="an-searchbox">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="anSearch" name="search" class="form-control" value="{{ $search }}" placeholder="Search tenant, category or text">
                        <button type="button" class="btn an-clear-search" id="anClearSearch" aria-label="Clear search" @if ($search === '') hidden @endif><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div class="an-filter">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="an-filter">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $dateFrom }}" aria-label="Date From"
                        @if ($dateErrors->has('date_from')) aria-invalid="true" aria-describedby="anDateError" @endif>
                </div>

                <div class="an-filter">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $dateTo }}" aria-label="Date To"
                        @if ($dateErrors->has('date_to')) aria-invalid="true" aria-describedby="anDateError" @endif>
                </div>

                <div class="an-filter">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <span id="anSummaryTrigger" title="Select complaints to summarize">
                        <button type="button" id="anGenerateSummary" class="btn btn-primary" disabled
                            data-url="{{ route('proprietor.complaints.summaries.store') }}" title="Select complaints to summarize">
                            <i class="bi bi-magic me-1" aria-hidden="true"></i><span id="anGenerateLabel">Generate Summary</span>
                        </button>
                    </span>
                    <button type="button" id="anSummaryHistory" class="btn btn-outline-secondary"
                        data-url="{{ route('proprietor.complaints.summaries.index') }}"><i class="bi bi-clock-history" aria-hidden="true"></i> History</button>
                </div>
            </form>
            @if ($dateErrors->any())
                <div class="small text-danger mt-2" id="anDateError" role="alert">{{ $dateErrors->first() }}</div>
            @endif
        </div>

        @if ($complaints->isEmpty())
            <div class="an-panel"><div class="an-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No complaints found.</div></div>
        @else
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <label class="an-selection">
                    <input type="checkbox" class="form-check-input mt-0 me-1" id="anSelectAll" aria-label="Select all visible complaints">
                    Select all
                </label>
                <span class="an-selection" id="anSelection" hidden>
                    <span id="anSelectedCount" aria-live="polite"></span>
                    <a class="ms-1" id="anClearSelection" href="#anSelectAll">Clear selection</a>
                </span>
                <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                    <button type="button" class="an-chip" id="anAttention" aria-pressed="false"><i class="bi bi-exclamation-triangle me-1"></i>Needs attention</button>
                    <div class="an-seg" role="group" aria-label="Switch view">
                        <button type="button" data-view="table" aria-pressed="true"><i class="bi bi-table me-1"></i>Table</button>
                        <button type="button" data-view="cards" aria-pressed="false"><i class="bi bi-grid me-1"></i>Cards</button>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div id="anTable" class="an-panel an-tablewrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle an-table">
                        <thead>
                            <tr>
                                <th scope="col" aria-label="Selection"></th>
                                <th>Tenant</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($complaints as $complaint)
                                @php $m = $meta($complaint); @endphp
                                <tr class="complaint-row an-item {{ $m->pKey ? 'is-' . $m->pKey : '' }} {{ $complaint->isUnread() ? 'is-unread' : '' }}"
                                    data-complaint-modal="#complaintModal{{ $complaint->id }}"
                                    data-attention="{{ $m->attention ? 1 : 0 }}"
                                    tabindex="0" aria-label="View complaint from {{ $complaint->tenant->name }}">
                                    <td>
                                        <input type="checkbox" class="form-check-input mt-0" data-select-complaint value="{{ $complaint->id }}"
                                            aria-label="Select complaint from {{ $complaint->tenant->name }}">
                                    </td>
                                    <td>
                                        <div class="t-name">{{ $complaint->tenant->name }}</div>
                                        <div class="t-sub">{{ $complaint->tenant->email }}</div>
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
                                        <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], $filterQuery)) }}" class="an-status complaint-status-form">
                                            @csrf
                                            @method('PATCH')
                                            <select class="form-select form-select-sm" name="status" aria-label="Complaint status">
                                                @foreach ($statusLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($displayStatus($complaint) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Update status" aria-label="Update status"><i class="bi bi-check2"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $complaint->created_at->format('M d, Y, g:i A') }}
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
                    <div class="col an-item" data-attention="{{ $m->attention ? 1 : 0 }}">
                        <article class="complaint-card {{ $m->pKey ? 'is-' . $m->pKey : '' }} {{ $complaint->isUnread() ? 'is-unread' : '' }}" data-complaint-modal="#complaintModal{{ $complaint->id }}" tabindex="0" aria-label="View complaint from {{ $complaint->tenant->name }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="d-flex align-items-start gap-2">
                                    <input type="checkbox" class="form-check-input" data-select-complaint value="{{ $complaint->id }}"
                                        aria-label="Select complaint from {{ $complaint->tenant->name }}">
                                    <div>
                                        <div class="t-name" style="font-size:.9rem">{{ $complaint->tenant->name }}</div>
                                        <div style="font-size:.72rem">{{ $complaint->created_at->format('M d, Y, g:i A') }}</div>
                                        <div class="text-muted" style="font-size:.72rem">{{ $complaint->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                                @if ($m->pKey)
                                    <span class="an-badge p-{{ $m->pKey }}"><i class="bi {{ $m->pIcon }}"></i>{{ $m->pLabel }}</span>
                                @endif
                            </div>
                            <div class="d-flex gap-1 flex-wrap">
                                <span class="an-badge"><i class="bi {{ $m->catIcon }}"></i>{{ ucfirst($complaint->category) }}</span>
                                <span class="an-badge s-{{ $displayStatus($complaint) }}">{{ $statusLabels[$displayStatus($complaint)] }}</span>
                            </div>
                            <p>{{ $complaint->description }}</p>
                            <form method="POST" action="{{ route('proprietor.complaints.update', array_merge(['complaint' => $complaint], $filterQuery)) }}" class="an-status complaint-status-form mt-auto">
                                @csrf
                                @method('PATCH')
                                <select class="form-select form-select-sm" name="status" aria-label="Complaint status">
                                    @foreach ($statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($displayStatus($complaint) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary btn-sm" title="Update status" aria-label="Update status"><i class="bi bi-check2"></i></button>
                            </form>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="an-panel an-empty mt-2" id="anNoMatch" hidden><i class="bi bi-search" aria-hidden="true"></i>No complaints on this page need attention.</div>

        @endif

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="an-count" id="anCount" data-total="{{ $complaints->total() }}" aria-live="polite">Showing {{ $complaints->count() }} of {{ $complaints->total() }}</div>
            @if ($complaints->hasPages())
                <div class="an-pagination ms-auto">
                    {{ $complaints->links() }}
                </div>
            @endif
        </div>

        @include('proprietor.complaints.summary-overlay')

        @foreach ($complaints as $complaint)
            <div class="modal fade complaint-detail-modal" id="complaintModal{{ $complaint->id }}"
                data-read-url="{{ route('proprietor.complaints.read', $complaint) }}" tabindex="-1" aria-labelledby="complaintModalLabel{{ $complaint->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="complaintModalLabel{{ $complaint->id }}">{{ ucfirst($complaint->category) }} Complaint</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="small text-danger mb-3" data-read-error role="alert" hidden>Could not mark this complaint as read. Reopen it to retry.</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="complaint-detail-label">Tenant</div>
                                    <div class="complaint-detail-value">{{ $complaint->tenant->name }}</div>
                                    <div class="text-muted small">{{ $complaint->tenant->email }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="complaint-detail-label">Status</div>
                                    <div class="complaint-detail-value">{{ $statusLabels[$displayStatus($complaint)] }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="complaint-detail-label">Timestamp</div>
                                    <div class="complaint-detail-value">{{ $complaint->created_at->format('M d, Y, g:i A') }}</div>
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
                const filters = document.getElementById('anFilters');
                const search = document.getElementById('anSearch');
                const clearSearch = document.getElementById('anClearSearch');
                const searchFocusKey = 'an-complaints-search-focus';
                let searchTimer;
                const selectionKey = 'an-complaints-selection-{{ auth()->id() }}';
                const filterState = () => JSON.stringify(Array.from(new FormData(filters).values(), value => value.trim()));
                const initialFilters = filterState();
                const selected = new Set();

                function restoreSelection() {
                    try {
                        const saved = JSON.parse(sessionStorage.getItem(selectionKey));
                        selected.clear();
                        if (saved?.filters === initialFilters && Array.isArray(saved.ids)) {
                            saved.ids.forEach(id => selected.add(id));
                        }
                    } catch (error) {
                        // Selection still works on this page without session storage.
                    }
                }
                restoreSelection();

                try {
                    const savedSearch = JSON.parse(sessionStorage.getItem(searchFocusKey));
                    sessionStorage.removeItem(searchFocusKey);
                    if (savedSearch && savedSearch.value.trim() === search.value.trim()) {
                        search.value = savedSearch.value;
                        search.focus({ preventScroll: true });
                        search.setSelectionRange(savedSearch.start, savedSearch.end, savedSearch.direction);
                    }
                } catch (error) {
                    // The filters still work when session storage is unavailable.
                }

                clearSearch.hidden = search.value === '';
                filters.addEventListener('submit', function () {
                    clearTimeout(searchTimer);
                    if (filterState() !== initialFilters) {
                        selected.clear();
                        syncSelection();
                    }
                    if (document.activeElement !== search) { return; }

                    try {
                        sessionStorage.setItem(searchFocusKey, JSON.stringify({
                            value: search.value,
                            start: search.selectionStart,
                            end: search.selectionEnd,
                            direction: search.selectionDirection,
                        }));
                    } catch (error) {
                        // Submit even if focus cannot be restored.
                    }
                });
                filters.addEventListener('change', function (event) {
                    if (event.target.matches('select, input[type="date"]')) {
                        filters.requestSubmit();
                    }
                });
                search.addEventListener('input', function (event) {
                    clearSearch.hidden = search.value === '';
                    clearTimeout(searchTimer);
                    if (!event.isComposing) {
                        searchTimer = setTimeout(() => filters.requestSubmit(), 300);
                    }
                });
                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' && !event.isComposing) {
                        event.preventDefault();
                        filters.requestSubmit();
                    }
                });
                clearSearch.addEventListener('click', function () {
                    search.value = '';
                    clearSearch.hidden = true;
                    search.focus();
                    filters.requestSubmit();
                });

                const openers = Array.from(document.querySelectorAll('.an [data-complaint-modal]'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                function openModal(opener) {
                    const modal = document.querySelector(opener.dataset.complaintModal);
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }

                openers.forEach(function (opener) {
                    opener.addEventListener('click', function (event) {
                        if (event.target.closest('form, button, select, input, a')) { return; }
                        openModal(opener);
                    });
                    opener.addEventListener('keydown', function (event) {
                        if (event.target !== opener || !['Enter', ' '].includes(event.key)) { return; }
                        event.preventDefault();
                        openModal(opener);
                    });
                });

                document.querySelectorAll('.an [data-read-url]').forEach(function (modal) {
                    modal.addEventListener('shown.bs.modal', async function () {
                        const unread = openers.filter(opener =>
                            opener.dataset.complaintModal === '#' + modal.id && opener.classList.contains('is-unread')
                        );
                        if (!unread.length) { return; }

                        const errorMessage = modal.querySelector('[data-read-error]');
                        errorMessage.hidden = true;
                        unread.forEach(opener => opener.classList.remove('is-unread'));

                        try {
                            const response = await fetch(modal.dataset.readUrl, {
                                method: 'PATCH',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            });
                            if (response.status !== 204) { throw new Error('Read status update failed'); }
                            document.dispatchEvent(new Event('complaint:read'));
                        } catch (error) {
                            unread.forEach(opener => opener.classList.add('is-unread'));
                            errorMessage.hidden = false;
                        }
                    });
                });

                const summarySelection = setupComplaintSummaries(selected, csrfToken, () => syncSelection());
                let syncSelection = summarySelection;
                syncSelection();
                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        restoreSelection();
                        syncSelection();
                    }
                });

                const table = document.getElementById('anTable');
                if (!table) { return; }

                const cards = document.getElementById('anCards');
                const attention = document.getElementById('anAttention');
                const count = document.getElementById('anCount');
                const noMatch = document.getElementById('anNoMatch');
                const viewButtons = document.querySelectorAll('.an [data-view]');
                const items = document.querySelectorAll('.an .an-item');
                const checkboxes = Array.from(document.querySelectorAll('.an [data-select-complaint]'));
                const selectAll = document.getElementById('anSelectAll');
                const selection = document.getElementById('anSelection');
                const selectedCount = document.getElementById('anSelectedCount');
                const clearSelection = document.getElementById('anClearSelection');

                function visibleCheckboxes() {
                    const activeView = table.hidden ? cards : table;
                    return checkboxes.filter(box => activeView.contains(box) && !box.closest('.an-item').hidden);
                }

                function updateSelection() {
                    checkboxes.forEach(box => { box.checked = selected.has(box.value); });
                    const visible = visibleCheckboxes();
                    const visibleSelected = visible.filter(box => box.checked).length;
                    selectAll.disabled = visible.length === 0;
                    selectAll.checked = visible.length > 0 && visibleSelected === visible.length;
                    selectAll.indeterminate = visibleSelected > 0 && visibleSelected < visible.length;
                    selection.hidden = selected.size === 0;
                    selectedCount.textContent = selected.size + ' selected';
                    summarySelection();
                }
                syncSelection = updateSelection;

                checkboxes.forEach(function (box) {
                    box.addEventListener('click', event => event.stopPropagation());
                    box.addEventListener('change', function () {
                        if (box.checked) { selected.add(box.value); }
                        else { selected.delete(box.value); }
                        updateSelection();
                    });
                });
                selectAll.addEventListener('change', function () {
                    visibleCheckboxes().forEach(function (box) {
                        if (selectAll.checked) { selected.add(box.value); }
                        else { selected.delete(box.value); }
                    });
                    updateSelection();
                });
                clearSelection.addEventListener('click', function (event) {
                    event.preventDefault();
                    selected.clear();
                    (selectAll.disabled ? attention : selectAll).focus();
                    updateSelection();
                });

                attention.addEventListener('click', function () {
                    const onlyAttention = attention.getAttribute('aria-pressed') !== 'true';
                    attention.setAttribute('aria-pressed', String(onlyAttention));
                    items.forEach(item => { item.hidden = onlyAttention && item.dataset.attention !== '1'; });
                    const shown = visibleCheckboxes().length;
                    count.textContent = 'Showing ' + shown + ' of ' + count.dataset.total;
                    noMatch.hidden = shown !== 0;
                    updateSelection();
                });

                function setView(view) {
                    table.hidden = view !== 'table';
                    cards.hidden = view !== 'cards';
                    viewButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
                    updateSelection();
                }

                viewButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        setView(button.dataset.view);
                        try { localStorage.setItem('an-complaints-view', button.dataset.view); } catch (error) {}
                    });
                });

                let savedView = null;
                try { savedView = localStorage.getItem('an-complaints-view'); } catch (error) {}
                setView(savedView === 'cards' ? 'cards' : 'table');

                @include('proprietor.complaints.summary-script')
            })();
        </script>
    @endpush
@endsection
