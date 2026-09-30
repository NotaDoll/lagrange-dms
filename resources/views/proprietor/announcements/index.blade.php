@extends('layouts.proprietor')

@section('title', 'Announcements')

@section('content')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .an {
            --border-color: #e2e8f0;
            --card-radius: 12px;
            --accent-blue: #2563eb;
            --accent-blue-bg: #dbeafe;
            --ink: #0f172a;
            --ink-2: #475569;
            --canvas: #f8fafc;
            --ok: #15803d;   --ok-bg: #dcfce7;
            --new: #b45309;  --new-bg: #fef3c7;
            --shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 4px 12px -6px rgba(15, 23, 42, .08);

            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--ink);
        }
        .an button, .an .btn { font-family: inherit; }
        .an .btn { border-radius: 8px; font-weight: 500; font-size: .875rem; }
        .an .btn-sm { font-size: .8rem; }
        .an .btn-primary {
            --bs-btn-bg: var(--accent-blue); --bs-btn-border-color: var(--accent-blue);
            --bs-btn-hover-bg: #1d4ed8; --bs-btn-hover-border-color: #1d4ed8;
            --bs-btn-active-bg: #1e40af; --bs-btn-active-border-color: #1e40af;
            --bs-btn-focus-shadow-rgb: 37, 99, 235;
        }
        .an-title { font-size: 1.6rem; font-weight: 600; margin: 0 0 .15rem; }
        .an-sub { color: var(--ink-2); font-size: .9rem; margin: 0; }

        /* Alert */
        .an-alert { display: flex; align-items: center; gap: .75rem; padding: .8rem 1rem; border-radius: var(--card-radius); border: 1px solid #bbf7d0; background: var(--ok-bg); color: #14532d; font-size: .9rem; }
        .an-alert .bi-check-circle-fill { color: var(--ok); font-size: 1.1rem; }
        .an-alert .btn-close { margin-left: auto; padding: .5rem; }

        /* KPIs */
        .an-kpi { background: #fff; border: 1px solid var(--border-color); border-radius: var(--card-radius); box-shadow: var(--shadow); padding: 1rem 1.1rem; height: 100%; display: flex; gap: .85rem; align-items: flex-start; }
        .an-icon { flex: 0 0 auto; width: 2.5rem; height: 2.5rem; border-radius: 10px; display: grid; place-items: center; font-size: 1.15rem; background: var(--accent-blue-bg); color: var(--accent-blue); }
        .an-icon.is-new { background: var(--new-bg); color: var(--new); }
        .an-icon.is-ok { background: var(--ok-bg); color: var(--ok); }
        .an-kpi-label { font-size: .8rem; color: var(--ink-2); }
        .an-kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.25; }
        .an-kpi-note { font-size: .78rem; color: var(--ink-2); }

        /* Toolbar */
        .an-count { font-size: .85rem; color: var(--ink-2); }
        .an-seg { display: inline-flex; padding: 3px; background: #f1f5f9; border-radius: 10px; }
        .an-seg button { border: 0; background: transparent; border-radius: 8px; padding: .35rem .8rem; font-size: .82rem; font-weight: 500; color: var(--ink-2); }
        .an-seg button[aria-pressed="true"] { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }

        /* Badges */
        .an-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; white-space: nowrap; background: #f1f5f9; color: var(--ink-2); }
        .an-badge.is-date { background: var(--accent-blue-bg); color: #1d4ed8; }
        .an-badge.is-new { background: var(--new-bg); color: var(--new); }
        .an-badge.is-live { background: var(--ok-bg); color: var(--ok); }
        .an-badge.is-live::before { content: ""; width: .45rem; height: .45rem; border-radius: 50%; background: currentColor; }

        /* Feed */
        .an-post { background: #fff; border: 1px solid var(--border-color); border-radius: var(--card-radius); box-shadow: var(--shadow); padding: 1.1rem 1.25rem; display: flex; gap: 1rem; align-items: flex-start; transition: border-color .18s, box-shadow .18s; }
        .an-post:hover { border-color: #cbd5e1; box-shadow: 0 10px 24px -12px rgba(15, 23, 42, .2); }
        .an-post-title { font-size: 1.05rem; font-weight: 600; margin: 0 0 .3rem; overflow-wrap: anywhere; }
        .an-excerpt { font-size: .875rem; color: #334155; line-height: 1.65; margin: 0 0 .75rem; overflow-wrap: anywhere; }
        .an-excerpt.is-empty { color: #94a3b8; font-style: italic; }
        .an-actions { display: flex; gap: .5rem; flex: 0 0 auto; }

        /* Table */
        .an-tablewrap { background: #fff; border: 1px solid var(--border-color); border-radius: var(--card-radius); box-shadow: var(--shadow); overflow: hidden; }
        .an-table { margin: 0; font-size: .875rem; }
        .an-table thead th { background: var(--canvas); color: var(--ink-2); font-size: .78rem; font-weight: 600; border-bottom: 1px solid var(--border-color); padding: .75rem 1rem; white-space: nowrap; }
        .an-table td { padding: .85rem 1rem; border-color: var(--border-color); vertical-align: middle; }
        .an-table tbody tr { transition: background-color .12s; }
        .an-table tbody tr:hover { background: #f8faff; }
        .an-table .t-title { font-weight: 600; }
        .an-table .t-excerpt { color: var(--ink-2); font-size: .8rem; max-width: 34rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .an-rel { display: block; font-size: .75rem; color: var(--ink-2); margin-top: .2rem; }

        /* Empty + pagination */
        .an-empty { text-align: center; background: #fff; border: 1px dashed #cbd5e1; border-radius: var(--card-radius); padding: 3.5rem 1.5rem; }
        .an-empty .an-icon { width: 3.5rem; height: 3.5rem; font-size: 1.6rem; border-radius: 16px; margin: 0 auto 1rem; }
        .an-empty h2 { font-size: 1.1rem; font-weight: 600; margin: 0 0 .35rem; }
        .an-empty p { color: var(--ink-2); font-size: .9rem; margin: 0 0 1.25rem; }
        .an-pager { display: flex; justify-content: center; margin-top: 1.5rem; }
        .an-pager .pagination { --bs-pagination-color: var(--accent-blue); --bs-pagination-active-bg: var(--accent-blue); --bs-pagination-active-border-color: var(--accent-blue); --bs-pagination-border-color: var(--border-color); --bs-pagination-font-size: .85rem; margin: 0; }
        .an-pager nav p, .an-pager .small.text-muted { font-size: .8rem; }

        .an a:focus-visible, .an button:focus-visible { outline: 2px solid var(--accent-blue); outline-offset: 2px; }
        @media (max-width: 575.98px) { .an-post { flex-direction: column; } .an-actions { width: 100%; } }
        @media (prefers-reduced-motion: reduce) { .an * { transition: none !important; } }
    </style>

    @php
        use Illuminate\Support\Str;

        $items = $announcements->getCollection();
        $latest = $items->first();
        $monthCount = $postedThisMonth ?? $items->filter(fn ($a) => $a->created_at->isSameMonth(now()))->count();

        $excerptOf = function ($a) {
            $attrs = $a->getAttributes();
            $body = $attrs['content'] ?? $attrs['body'] ?? $attrs['message'] ?? $attrs['description'] ?? null;
            return $body ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 170) : null;
        };
    @endphp

    <div class="an">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <h1 class="an-title">Announcements</h1>
                <p class="an-sub">Post updates and notices that your tenants will see.</p>
            </div>
            <a href="{{ route('proprietor.announcements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New announcement</a>
        </div>

        @if (session('success'))
            <div class="an-alert alert alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- KPIs --}}
        <div class="row g-3 row-cols-1 row-cols-md-3 mb-4">
            <div class="col">
                <div class="an-kpi">
                    <div class="an-icon" aria-hidden="true"><i class="bi bi-megaphone"></i></div>
                    <div>
                        <div class="an-kpi-label">Total announcements</div>
                        <div class="an-kpi-value">{{ $announcements->total() }}</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="an-kpi">
                    <div class="an-icon is-new" aria-hidden="true"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="an-kpi-label">Posted this month</div>
                        <div class="an-kpi-value">{{ $monthCount }}</div>
                        <div class="an-kpi-note">{{ now()->format('F Y') }}</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="an-kpi">
                    <div class="an-icon is-ok" aria-hidden="true"><i class="bi bi-broadcast"></i></div>
                    <div>
                        <div class="an-kpi-label">Status</div>
                        <div class="mt-1"><span class="an-badge is-live">{{ $announcements->total() > 0 ? 'Live' : 'No posts yet' }}</span></div>
                        <div class="an-kpi-note mt-1">{{ $latest ? 'Latest post ' . $latest->created_at->diffForHumans() : 'Create your first announcement' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($announcements->isEmpty())
            <div class="an-empty">
                <div class="an-icon" aria-hidden="true"><i class="bi bi-megaphone"></i></div>
                <h2>No announcements yet</h2>
                <p>Share news, reminders or rule changes with your tenants.</p>
                <a href="{{ route('proprietor.announcements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create announcement</a>
            </div>
        @else
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="an-count">Showing {{ $announcements->firstItem() }}–{{ $announcements->lastItem() }} of {{ $announcements->total() }}</div>
                <div class="an-seg" role="group" aria-label="Switch view">
                    <button type="button" data-view="feed" aria-pressed="true"><i class="bi bi-card-text me-1"></i>Feed</button>
                    <button type="button" data-view="table" aria-pressed="false"><i class="bi bi-table me-1"></i>Table</button>
                </div>
            </div>

            {{-- Feed --}}
            <div id="anFeed" class="d-grid gap-3">
                @foreach ($announcements as $announcement)
                    @php $excerpt = $excerptOf($announcement); @endphp
                    <article class="an-post">
                        <div class="an-icon d-none d-sm-grid" aria-hidden="true"><i class="bi bi-megaphone"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <h2 class="an-post-title">{{ $announcement->title }}</h2>
                            @if ($excerpt)
                                <p class="an-excerpt">{{ $excerpt }}</p>
                            @else
                                <p class="an-excerpt is-empty">No preview available.</p>
                            @endif
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="an-badge is-date"><i class="bi bi-calendar3"></i>{{ $announcement->created_at->format('M j, Y') }}</span>
                                <span class="an-badge"><i class="bi bi-clock"></i>{{ $announcement->created_at->diffForHumans() }}</span>
                                @if ($announcement->created_at->gt(now()->subDays(7)))
                                    <span class="an-badge is-new">New</span>
                                @endif
                            </div>
                        </div>
                        <div class="an-actions">
                            <a href="{{ route('proprietor.announcements.edit', $announcement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                            <form action="{{ route('proprietor.announcements.destroy', $announcement) }}" method="POST" onsubmit="return confirm('Delete this announcement?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Table --}}
            <div id="anTable" class="an-tablewrap" hidden>
                <div class="table-responsive">
                    <table class="table table-hover align-middle an-table">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Posted</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($announcements as $announcement)
                                @php $excerpt = $excerptOf($announcement); @endphp
                                <tr>
                                    <td>
                                        <div class="t-title">{{ $announcement->title }}
                                            @if ($announcement->created_at->gt(now()->subDays(7)))
                                                <span class="an-badge is-new ms-1">New</span>
                                            @endif
                                        </div>
                                        @if ($excerpt)<div class="t-excerpt">{{ $excerpt }}</div>@endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="an-badge is-date"><i class="bi bi-calendar3"></i>{{ $announcement->created_at->format('M j, Y') }}</span>
                                        <span class="an-rel">{{ $announcement->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('proprietor.announcements.edit', $announcement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <form action="{{ route('proprietor.announcements.destroy', $announcement) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="an-pager">{{ $announcements->links() }}</div>
        @endif
    </div>

    <script>
        (function () {
            var feed = document.getElementById('anFeed'), table = document.getElementById('anTable');
            if (feed && table) {
                var buttons = document.querySelectorAll('.an [data-view]');
                var setView = function (v) {
                    feed.hidden = v !== 'feed';
                    table.hidden = v !== 'table';
                    buttons.forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.view === v ? 'true' : 'false'); });
                    try { localStorage.setItem('an-view', v); } catch (e) {}
                };
                buttons.forEach(function (b) { b.addEventListener('click', function () { setView(b.dataset.view); }); });
                var saved = null;
                try { saved = localStorage.getItem('an-view'); } catch (e) {}
                setView(saved === 'table' ? 'table' : 'feed');
            }
            // Fallback so the alert still closes if Bootstrap's JS isn't loaded
            document.querySelectorAll('.an-alert .btn-close').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.bootstrap) btn.closest('.an-alert').remove();
                });
            });
        })();
    </script>
@endsection
