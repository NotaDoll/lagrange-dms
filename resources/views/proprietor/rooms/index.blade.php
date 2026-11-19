@extends('layouts.proprietor')

@section('title', 'Rooms')

@section('content')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .rm {
            /* Page colors follow the shared theme (partials/theme.blade.php). */
            --rm-ink: var(--theme-text-dark);   --rm-ink-2: var(--theme-text-muted);   --rm-line: var(--theme-border);
            --rm-canvas: #fcf8fb; --rm-surface: #fff;   --rm-accent: var(--theme-plum);
            --rm-radius: var(--theme-radius);
            --rm-shadow: var(--theme-shadow);

            --rm-vacant: #15803d;  --rm-vacant-bg: #dcfce7;
            --rm-occ:    var(--theme-plum);  --rm-occ-bg:    var(--theme-pink-light);
            --rm-maint:  #b45309;  --rm-maint-bg:  #fef3c7;
            --rm-part:   #0369a1;  --rm-part-bg:   #e0f2fe;
            --rm-none:   #475569;  --rm-none-bg:   #f1f5f9;

            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--rm-ink);
        }
        .rm button, .rm input, .rm select, .rm .btn, .rm .form-control, .rm .form-select, .rm .modal { font-family: inherit; }
        .rm .form-control, .rm .form-select { font-size: .875rem; }
        .rm .modal-content { border: 1px solid var(--rm-line); border-radius: var(--rm-radius); }

        .rm-title { font-size: 1.6rem; font-weight: 600; margin: 0 0 .15rem; }
        .rm-sub { color: var(--rm-ink-2); font-size: .9rem; margin: 0; }

        /* Stats */
        .rm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr)); gap: .9rem; }
        .rm-stat { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); padding: 1rem 1.1rem; height: 100%; display: flex; gap: .85rem; align-items: flex-start; }
        .rm-icon { flex: 0 0 auto; width: 2.5rem; height: 2.5rem; border-radius: 10px; display: grid; place-items: center; font-size: 1.15rem; background: var(--rm-none-bg); color: var(--rm-none); }
        .rm-icon.is-occ { background: var(--rm-occ-bg); color: var(--rm-occ); }
        .rm-icon.is-vacant { background: var(--rm-vacant-bg); color: var(--rm-vacant); }
        .rm-icon.is-part { background: var(--rm-part-bg); color: var(--rm-part); }
        .rm-stat-label { font-size: .8rem; color: var(--rm-ink-2); }
        .rm-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.25; }
        .rm-meter { height: .4rem; background: var(--rm-line); border-radius: 999px; overflow: hidden; margin-top: .35rem; }
        .rm-meter > span { display: block; height: 100%; background: var(--rm-occ); border-radius: inherit; }
        @media (max-width: 575.98px) {
            .rm-stats { grid-template-columns: repeat(auto-fit, minmax(min(100%, 8.5rem), 1fr)); gap: .65rem; }
            .rm-stat { padding: .7rem; gap: .45rem; }
            .rm-icon { width: 1.8rem; height: 1.8rem; font-size: .95rem; }
        }

        /* Toolbar */
        .rm-bar { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); padding: 1rem 1.1rem; }
        .rm-bar label { font-size: .75rem; color: var(--rm-ink-2); margin-bottom: .2rem; }
        .rm-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); gap: .75rem; align-items: end; }
        .rm-seg { display: inline-flex; padding: 3px; background: var(--theme-pink-light); border-radius: var(--theme-btn-radius); }
        .rm-seg button { border: 0; background: transparent; border-radius: calc(var(--theme-btn-radius) - 3px); padding: .35rem .8rem; font-size: .82rem; font-weight: 500; color: var(--rm-ink-2); }
        .rm-seg button[aria-pressed="true"] { background: var(--rm-surface); color: var(--rm-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .rm-count { font-size: .82rem; color: var(--rm-ink-2); }

        /* Status pills and dots */
        .rm-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; background: var(--rm-none-bg); color: var(--rm-none); }
        .rm-pill::before { content: ""; flex: 0 0 .45rem; height: .45rem; border-radius: 50%; background: currentColor; }
        .st-full, .b-occupied { background: var(--rm-occ-bg); color: var(--rm-occ); }
        .st-vacant, .b-available { background: var(--rm-vacant-bg); color: var(--rm-vacant); }
        .st-partial { background: var(--rm-part-bg); color: var(--rm-part); }
        .st-maintenance, .b-other { background: var(--rm-maint-bg); color: var(--rm-maint); }

        /* Room cards */
        .rm-grid:not([hidden]) { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 19rem), 1fr)); gap: 1rem; }
        .rm-card { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); height: 100%; display: flex; flex-direction: column; transition: box-shadow .18s, border-color .18s; }
        .rm-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 24px -12px rgba(15, 23, 42, .2); }
        .rm-card-head { display: flex; align-items: flex-start; flex-wrap: wrap; gap: .55rem .8rem; padding: 1rem 1.1rem; }
        .rm-card-head > div { flex: 1 1 10rem; min-width: 0; }
        .rm-room { font-size: 1.08rem; font-weight: 600; margin: 0 0 .25rem; overflow-wrap: anywhere; }
        .rm-meta { font-size: .8rem; color: var(--rm-ink-2); }
        .rm-card-body { padding: 0 1.1rem 1rem; flex: 1; }
        .rm-card-foot { padding: .85rem 1.1rem; display: flex; flex-wrap: wrap; gap: .5rem; border-top: 1px solid var(--rm-line); }

        /* Bed management dialog */
        .rm-dialog .modal-header { align-items: flex-start; }
        .rm-dialog .modal-title { overflow-wrap: anywhere; }
        .rm-dialog .modal-body { padding: clamp(1rem, 3vw, 1.5rem); }
        .rm-dialog .modal-content { min-width: 0; }
        .rm-bed-list { padding: 0; margin: 0; list-style: none; }
        .rm-bed { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem 1.25rem; padding: .9rem 0; border-bottom: 1px solid var(--rm-line); }
        .rm-bed:last-child { border-bottom: 0; }
        .rm-bed-info { flex: 1 1 13rem; min-width: 0; overflow-wrap: anywhere; }
        .rm-bed-label { font-size: .875rem; font-weight: 600; margin: 0; }
        .rm-bed-who { font-size: .875rem; color: var(--rm-ink-2); margin: .35rem 0 0; }
        .rm-bed-actions, .rm-bed-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .rm-bed-status .form-select { width: auto; max-width: 100%; }
        .rm-bed-actions, .rm-bed-status { max-width: 100%; }
        .rm-assign { background: var(--rm-canvas); border: 1px solid var(--rm-line); border-radius: 8px; padding: .75rem 1rem; margin-bottom: .65rem; }
        .rm-assign summary { font-size: .875rem; font-weight: 600; cursor: pointer; }
        .rm-assign[open] summary { margin-bottom: .75rem; }
        .rm-assign summary:focus-visible { outline: 2px solid var(--rm-accent); outline-offset: 2px; }
        .rm-assign-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); gap: .75rem; }
        .rm-assign-fields > div { min-width: 0; }
        @media (pointer: coarse) { .rm-dialog .btn, .rm-dialog select { min-height: 44px; } }

        /* Table */
        .rm-tablewrap { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); overflow: hidden; }
        .rm-table { margin: 0; font-size: .875rem; min-width: 42rem; }
        .rm-table thead th { background: var(--rm-canvas); color: var(--rm-ink-2); font-size: .78rem; font-weight: 600; border-bottom: 1px solid var(--rm-line); padding: .75rem 1rem; white-space: nowrap; }
        .rm-table td { padding: .8rem 1rem; border-color: var(--rm-line); vertical-align: middle; }
        .rm-table tbody tr:hover { background: #fafbfe; }
        .rm-empty { text-align: center; color: var(--rm-ink-2); padding: 3rem 1rem; background: var(--rm-surface); border: 1px dashed #cbd5e1; border-radius: var(--rm-radius); }
        .rm-empty .bi { display: block; font-size: 1.75rem; margin-bottom: .5rem; color: #94a3b8; }

        .rm a:focus-visible, .rm button:focus-visible { outline: 2px solid var(--rm-accent); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { .rm * { transition: none !important; } }
    </style>

    @php
        $stateLabels = ['full' => 'Fully occupied', 'partial' => 'Partially available', 'vacant' => 'Fully vacant', 'maintenance' => 'Maintenance', 'empty' => 'No beds'];

        $vm = $rooms->map(function ($room) {
            $beds = $room->beds;
            $total = $beds->count();
            $occ = $beds->where('status', 'occupied')->count();
            $vac = $beds->where('status', 'available')->count();
            $mnt = $total - $occ - $vac;
            $state = $total === 0 ? 'empty'
                : ($mnt === $total ? 'maintenance'
                : ($vac === 0 && $mnt === 0 ? 'full'
                : ($occ === 0 && $mnt === 0 ? 'vacant' : 'partial')));
            return (object) [
                'room' => $room, 'occ' => $occ, 'vac' => $vac, 'state' => $state,
            ];
        });

        $filteredRoomCount = $rooms->total();
    @endphp

    <div class="rm" id="rm">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <h1 class="rm-title">Room Management</h1>
                <p class="rm-sub">Review room availability and manage bed assignments.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('proprietor.rooms.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add new room</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold">Please check the room or bed details.</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Summary --}}
        <div class="rm-stats mb-4">
            <div class="col"><div class="rm-stat"><div class="rm-icon" aria-hidden="true"><i class="bi bi-door-closed"></i></div><div><div class="rm-stat-label">Total rooms</div><div class="rm-stat-value">{{ $totalRooms }}</div></div></div></div>
            <div class="col"><div class="rm-stat"><div class="rm-icon is-part" aria-hidden="true"><i class="bi bi-grid-3x3-gap"></i></div><div><div class="rm-stat-label">Total beds</div><div class="rm-stat-value">{{ $totalBeds }}</div></div></div></div>
            <div class="col"><div class="rm-stat"><div class="rm-icon is-occ" aria-hidden="true"><i class="bi bi-person-check"></i></div><div><div class="rm-stat-label">Occupied beds</div><div class="rm-stat-value">{{ $occupiedBeds }}</div></div></div></div>
            <div class="col"><div class="rm-stat"><div class="rm-icon is-vacant" aria-hidden="true"><i class="bi bi-door-open"></i></div><div><div class="rm-stat-label">Vacant beds</div><div class="rm-stat-value">{{ $vacantBeds }}</div></div></div></div>
            <div class="col">
                <div class="rm-stat">
                    <div class="rm-icon is-occ" aria-hidden="true"><i class="bi bi-pie-chart"></i></div>
                    <div class="flex-grow-1">
                        <div class="rm-stat-label">Occupancy rate</div>
                        <div class="rm-stat-value">{{ $rate }}%</div>
                        <div class="rm-meter" role="progressbar" aria-label="Occupancy rate" aria-valuenow="{{ round($rate) }}" aria-valuemin="0" aria-valuemax="100"><span @style(['width: ' . $rate . '%'])></span></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters and view toggle --}}
        <div class="rm-bar mb-3">
            <div class="collapse show" id="rmFilters">
                <form id="rmFilterForm" method="GET" action="{{ route('proprietor.rooms.index') }}" class="rm-filters mb-3">
                    <div>
                        <label for="rmSearch">Search rooms or tenants</label>
                        <input type="search" id="rmSearch" name="search" value="{{ $search }}" class="form-control" placeholder="Room number or tenant name">
                    </div>
                    <div>
                        <label for="rmType">Room Type</label>
                        <select id="rmType" name="type" class="form-select">
                            <option value="">All types</option>
                            @foreach ($roomTypes as $roomType)
                                <option value="{{ $roomType }}" @selected($type === $roomType)>{{ $roomType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="rmState">Availability</label>
                        <select id="rmState" name="availability" class="form-select">
                            <option value="">All statuses</option>
                            <option value="full" @selected($availability === 'full')>Fully occupied</option>
                            <option value="partial" @selected($availability === 'partial')>Partially available</option>
                            <option value="vacant" @selected($availability === 'vacant')>Fully vacant</option>
                            <option value="maintenance" @selected($availability === 'maintenance')>Maintenance</option>
                            <option value="empty" @selected($availability === 'empty')>No beds</option>
                        </select>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply filters</button>
                        <a href="{{ route('proprietor.rooms.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear</a>
                    </div>
                </form>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="rm-count" aria-live="polite">Showing {{ $rooms->count() }} of {{ $filteredRoomCount }} matching rooms ({{ $totalRooms }} total)</div>
                <div class="rm-seg" role="group" aria-label="Switch view">
                    <button type="button" data-view="grid" aria-pressed="true"><i class="bi bi-grid me-1"></i>Card grid</button>
                    <button type="button" data-view="table" aria-pressed="false"><i class="bi bi-table me-1"></i>Data table</button>
                </div>
            </div>
        </div>

        @if ($vm->isEmpty())
            <div class="rm-empty"><i class="bi bi-door-closed" aria-hidden="true"></i>
                @if ($totalRooms === 0)
                    No rooms yet. <a href="{{ route('proprietor.rooms.create') }}">Add your first room</a>.
                @else
                    No rooms match these filters. <a href="{{ route('proprietor.rooms.index') }}">Clear filters</a>.
                @endif
            </div>
        @else
            {{-- Card grid --}}
            <div id="rmGrid" class="rm-grid">
                @foreach ($vm as $v)
                    @php($room = $v->room)
                    <article class="rm-card">
                        <div class="rm-card-head">
                            <div>
                                <h2 class="rm-room">{{ $room->displayName() }}</h2>
                                <div class="rm-meta">PHP {{ number_format((float) $room->monthly_rate, 2) }} per month</div>
                            </div>
                            <span class="rm-pill st-{{ $v->state }}">{{ $stateLabels[$v->state] }}</span>
                        </div>
                        <div class="rm-card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-2 rm-meta mb-2">
                                <span>{{ $v->occ }} of {{ $room->capacity }} beds occupied</span>
                                <span>{{ $v->vac }} available</span>
                            </div>
                            @if ($room->capacity > 0)
                                @php($occupancy = min(100, round($v->occ / $room->capacity * 100)))
                                <div class="rm-meter" role="progressbar" aria-label="Room occupancy" aria-valuenow="{{ $occupancy }}" aria-valuemin="0" aria-valuemax="100">
                                    <span @style(['width: ' . $occupancy . '%'])></span>
                                </div>
                            @endif
                        </div>
                        <div class="rm-card-foot">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomBeds{{ $room->id }}" aria-label="Manage beds for {{ $room->displayName() }}">Manage beds</button>
                            <a href="{{ route('proprietor.rooms.edit', $room) }}" class="btn btn-outline-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit room</a>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Data table --}}
            <div id="rmTable" class="rm-tablewrap" hidden>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Room management table">
                    <table class="table rm-table">
                        <thead>
                            <tr>
                                <th scope="col">Room</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Occupied beds</th>
                                <th scope="col">Monthly rate</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vm as $v)
                                @php($room = $v->room)
                                <tr>
                                    <td class="fw-semibold">{{ $room->displayName() }}</td>
                                    <td>{{ $room->capacity }}</td>
                                    <td>{{ $v->occ }} / {{ $room->capacity }}</td>
                                    <td>PHP {{ number_format((float) $room->monthly_rate, 2) }}</td>
                                    <td><span class="rm-pill st-{{ $v->state }}">{{ $stateLabels[$v->state] }}</span></td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#roomBeds{{ $room->id }}" aria-label="Manage beds for {{ $room->displayName() }}">Manage beds</button>
                                            <a href="{{ route('proprietor.rooms.edit', $room) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Edit room</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @endif

        @if ($rooms->hasPages())
            <div class="mt-3">{{ $rooms->links() }}</div>
        @endif
    </div>

    @foreach ($vm as $v)
        @include('proprietor.rooms._beds-dialog')
    @endforeach

    <script>
        (function () {
            var root = document.getElementById('rm');
            if (!root) return;
            var $ = function (id) { return document.getElementById(id); };
            var filterForm = $('rmFilterForm');
            [$('rmType'), $('rmState')].forEach(function (select) {
                select.addEventListener('change', function () { filterForm.requestSubmit(); });
            });

            var grid = $('rmGrid'), table = $('rmTable');
            if (!grid) return;

            function setView(view) {
                grid.hidden = view !== 'grid';
                table.hidden = view !== 'table';
                root.querySelectorAll('[data-view]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b.dataset.view === view ? 'true' : 'false');
                });
                try { localStorage.setItem('rm-view', view); } catch (e) {}
            }
            root.querySelectorAll('[data-view]').forEach(function (b) {
                b.addEventListener('click', function () { setView(b.dataset.view); });
            });
            var saved = null;
            try { saved = localStorage.getItem('rm-view'); } catch (e) {}
            setView(saved === 'table' ? 'table' : 'grid');

            document.querySelectorAll('.rm-dialog [data-assign-bed]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var panel = $(button.getAttribute('aria-controls'));
                    panel.open = true;
                    panel.querySelector('[name="bed_id"]').value = button.dataset.assignBed;
                    var tenant = panel.querySelector('[name="tenant_id"]');
                    if (tenant.tomselect) {
                        tenant.tomselect.focus();
                    } else {
                        tenant.focus();
                    }
                });
            });

        })();
    </script>
@endsection

@include('partials.searchable-select')
