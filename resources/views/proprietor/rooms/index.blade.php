@extends('layouts.proprietor')

@section('title', 'Rooms')

@section('content')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .rm {
            --rm-ink: #0f172a;   --rm-ink-2: #475569;   --rm-line: #e2e8f0;
            --rm-canvas: #f8fafc; --rm-surface: #fff;   --rm-accent: #4338ca;
            --rm-radius: 12px;
            --rm-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 4px 12px -6px rgba(15, 23, 42, .08);

            --rm-vacant: #15803d;  --rm-vacant-bg: #dcfce7;
            --rm-occ:    #4338ca;  --rm-occ-bg:    #e0e7ff;
            --rm-maint:  #b45309;  --rm-maint-bg:  #fef3c7;
            --rm-part:   #0369a1;  --rm-part-bg:   #e0f2fe;
            --rm-none:   #475569;  --rm-none-bg:   #f1f5f9;

            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--rm-ink);
        }
        .rm button, .rm input, .rm select, .rm .btn, .rm .form-control, .rm .form-select, .rm .modal { font-family: inherit; }
        .rm .btn-primary {
            --bs-btn-bg: var(--rm-accent); --bs-btn-border-color: var(--rm-accent);
            --bs-btn-hover-bg: #3730a3; --bs-btn-hover-border-color: #3730a3;
            --bs-btn-active-bg: #312e81; --bs-btn-active-border-color: #312e81;
            --bs-btn-focus-shadow-rgb: 67, 56, 202;
        }
        .rm .btn { border-radius: 8px; font-weight: 500; font-size: .875rem; }
        .rm .btn-sm { font-size: .8rem; }
        .rm .form-control, .rm .form-select { border-color: var(--rm-line); border-radius: 8px; font-size: .875rem; }
        .rm .form-control:focus, .rm .form-select:focus { border-color: #a5b4fc; box-shadow: 0 0 0 .2rem rgba(67, 56, 202, .15); }
        .rm .modal-content { border: 1px solid var(--rm-line); border-radius: var(--rm-radius); }

        .rm-title { font-size: 1.6rem; font-weight: 600; margin: 0 0 .15rem; }
        .rm-sub { color: var(--rm-ink-2); font-size: .9rem; margin: 0; }

        /* Stats */
        .rm-stat { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); padding: 1rem 1.1rem; height: 100%; display: flex; gap: .85rem; align-items: flex-start; }
        .rm-icon { flex: 0 0 auto; width: 2.5rem; height: 2.5rem; border-radius: 10px; display: grid; place-items: center; font-size: 1.15rem; background: var(--rm-none-bg); color: var(--rm-none); }
        .rm-icon.is-occ { background: var(--rm-occ-bg); color: var(--rm-occ); }
        .rm-icon.is-vacant { background: var(--rm-vacant-bg); color: var(--rm-vacant); }
        .rm-icon.is-part { background: var(--rm-part-bg); color: var(--rm-part); }
        .rm-stat-label { font-size: .8rem; color: var(--rm-ink-2); }
        .rm-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.25; }
        .rm-meter { height: .4rem; background: var(--rm-line); border-radius: 999px; overflow: hidden; margin-top: .35rem; min-width: 5rem; }
        .rm-meter > span { display: block; height: 100%; background: var(--rm-occ); border-radius: inherit; }

        /* Toolbar */
        .rm-bar { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); padding: 1rem 1.1rem; }
        .rm-bar label { font-size: .75rem; color: var(--rm-ink-2); margin-bottom: .2rem; }
        .rm-seg { display: inline-flex; padding: 3px; background: var(--rm-none-bg); border-radius: 10px; }
        .rm-seg button { border: 0; background: transparent; border-radius: 8px; padding: .35rem .8rem; font-size: .82rem; font-weight: 500; color: var(--rm-ink-2); }
        .rm-seg button[aria-pressed="true"] { background: var(--rm-surface); color: var(--rm-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .rm-count { font-size: .82rem; color: var(--rm-ink-2); }

        /* Status pills and dots */
        .rm-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; white-space: nowrap; background: var(--rm-none-bg); color: var(--rm-none); }
        .rm-pill::before { content: ""; width: .45rem; height: .45rem; border-radius: 50%; background: currentColor; }
        .st-full, .b-occupied { background: var(--rm-occ-bg); color: var(--rm-occ); }
        .st-vacant, .b-available { background: var(--rm-vacant-bg); color: var(--rm-vacant); }
        .st-partial { background: var(--rm-part-bg); color: var(--rm-part); }
        .st-maintenance, .b-other { background: var(--rm-maint-bg); color: var(--rm-maint); }

        /* Room cards */
        .rm-card { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); height: 100%; display: flex; flex-direction: column; transition: box-shadow .18s, border-color .18s; }
        .rm-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 24px -12px rgba(15, 23, 42, .2); }
        .rm-card.is-flash { border-color: var(--rm-accent); box-shadow: 0 0 0 3px rgba(67, 56, 202, .25); }
        .rm-card-head { padding: 1rem 1.1rem .75rem; }
        .rm-room { font-size: 1.05rem; font-weight: 600; margin: 0; }
        .rm-meta { font-size: .8rem; color: var(--rm-ink-2); }
        .rm-card-body { padding: 0 1.1rem; flex: 1; }
        .rm-bed { display: flex; align-items: center; gap: .7rem; padding: .6rem 0; border-top: 1px solid var(--rm-line); }
        .rm-bed-label { font-size: .8rem; font-weight: 600; width: 3.2rem; flex: 0 0 auto; }
        .rm-bed-who { flex: 1; min-width: 0; display: flex; align-items: center; gap: .5rem; font-size: .85rem; }
        .rm-bed-who span.n { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rm-bed-free { color: var(--rm-vacant); font-weight: 500; }
        .rm-avatar { flex: 0 0 auto; width: 1.8rem; height: 1.8rem; border-radius: 50%; display: grid; place-items: center; font-size: .68rem; font-weight: 600; background: var(--rm-occ-bg); color: var(--rm-occ); }
        .rm-avatar.is-free { background: var(--rm-vacant-bg); color: var(--rm-vacant); }
        .rm-avatar.is-other { background: var(--rm-maint-bg); color: var(--rm-maint); }
        .rm-card-foot { padding: .75rem 1.1rem 1rem; display: flex; gap: .5rem; border-top: 1px solid var(--rm-line); margin-top: .35rem; }

        /* Table */
        .rm-tablewrap { background: var(--rm-surface); border: 1px solid var(--rm-line); border-radius: var(--rm-radius); box-shadow: var(--rm-shadow); overflow: hidden; }
        .rm-table { margin: 0; font-size: .875rem; }
        .rm-table thead th { background: var(--rm-canvas); color: var(--rm-ink-2); font-size: .78rem; font-weight: 600; border-bottom: 1px solid var(--rm-line); padding: .75rem 1rem; white-space: nowrap; }
        .rm-table td { padding: .8rem 1rem; border-color: var(--rm-line); vertical-align: middle; }
        .rm-table tbody tr:hover { background: #fafbfe; }
        .rm-empty { text-align: center; color: var(--rm-ink-2); padding: 3rem 1rem; background: var(--rm-surface); border: 1px dashed #cbd5e1; border-radius: var(--rm-radius); }
        .rm-empty .bi { display: block; font-size: 1.75rem; margin-bottom: .5rem; color: #94a3b8; }

        .rm a:focus-visible, .rm button:focus-visible { outline: 2px solid var(--rm-accent); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { .rm * { transition: none !important; } }
    </style>

    @php
        use Illuminate\Support\Str;

        $initials = fn ($name) => Str::of($name)->squish()->explode(' ')->filter()->take(2)
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');

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
            $names = $beds->map(fn ($b) => $b->currentAssignment?->tenant?->user?->name)->filter()->implode(' ');
            return (object) [
                'room' => $room, 'total' => $total, 'occ' => $occ, 'vac' => $vac, 'state' => $state,
                'search' => Str::lower($room->room_number . ' ' . $names),
                'type' => $room->gender ?? $room->room_type ?? null,
            ];
        });

        $totalRooms = $vm->count();
        $totalBeds = $vm->sum('total');
        $occupiedBeds = $vm->sum('occ');
        $vacantBeds = $vm->sum('vac');
        $rate = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100, 1) : 0;

        $floors = $vm->map(fn ($v) => $v->room->floor)->unique()->sort()->values();
        $types = $vm->pluck('type')->filter()->unique()->sort()->values();
    @endphp

    <div class="rm" id="rm">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <h1 class="rm-title">Room Management</h1>
                <p class="rm-sub">See who is in every bed, and assign vacant beds in one click.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#rmFilters" aria-expanded="true" aria-controls="rmFilters"><i class="bi bi-funnel me-1"></i>Filters</button>
                <button type="button" class="btn btn-outline-secondary" id="rmExport"><i class="bi bi-download me-1"></i>Export CSV</button>
                <a href="{{ route('proprietor.rooms.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add new room</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold">Please check the room assignment details.</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Summary --}}
        <div class="row g-3 row-cols-2 row-cols-lg-3 row-cols-xl-5 mb-4">
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
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-6 col-xl-3">
                        <label for="rmSearch">Search</label>
                        <input type="search" id="rmSearch" class="form-control" placeholder="Room number or tenant name">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="rmFloor">Floor</label>
                        <select id="rmFloor" class="form-select">
                            <option value="">All floors</option>
                            @foreach ($floors as $f)<option value="{{ $f }}">Floor {{ $f }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-3">
                        <label for="rmState">Availability</label>
                        <select id="rmState" class="form-select">
                            <option value="">All statuses</option>
                            <option value="full">Fully occupied</option>
                            <option value="partial">Partially available</option>
                            <option value="vacant">Fully vacant</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    @if ($types->isNotEmpty())
                        <div class="col-md-6 col-xl-2">
                            <label for="rmType">Room type / gender</label>
                            <select id="rmType" class="form-select">
                                <option value="">All</option>
                                @foreach ($types as $t)<option value="{{ Str::lower($t) }}">{{ Str::headline($t) }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="rmReset">Reset</button></div>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="rm-count" id="rmCount" aria-live="polite">Showing {{ $totalRooms }} of {{ $totalRooms }} rooms</div>
                <div class="rm-seg" role="group" aria-label="Switch view">
                    <button type="button" data-view="grid" aria-pressed="true"><i class="bi bi-grid me-1"></i>Card grid</button>
                    <button type="button" data-view="table" aria-pressed="false"><i class="bi bi-table me-1"></i>Data table</button>
                </div>
            </div>
        </div>

        @if ($vm->isEmpty())
            <div class="rm-empty"><i class="bi bi-door-closed" aria-hidden="true"></i>No rooms yet. <a href="{{ route('proprietor.rooms.create') }}">Add your first room</a>.</div>
        @else
            {{-- Card grid --}}
            <div id="rmGrid" class="row g-3">
                @foreach ($vm as $v)
                    @php $room = $v->room; @endphp
                    <div class="col-md-6 col-xl-4 rm-item" id="room-{{ $room->id }}"
                         data-floor="{{ $room->floor }}" data-state="{{ $v->state }}" data-type="{{ Str::lower((string) $v->type) }}" data-search="{{ $v->search }}">
                        <article class="rm-card">
                            <div class="rm-card-head d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h2 class="rm-room">Room {{ $room->room_number }}</h2>
                                    <div class="rm-meta">Floor {{ $room->floor }} &middot; PHP {{ number_format((float) $room->monthly_rate, 2) }} per month</div>
                                </div>
                                <span class="rm-pill st-{{ $v->state }}">{{ $stateLabels[$v->state] }}</span>
                            </div>
                            <div class="px-3 pb-2">
                                <div class="d-flex justify-content-between rm-meta mb-1"><span>{{ $v->occ }} of {{ $room->capacity }} beds occupied</span></div>
                                <div class="rm-meter"><span @style(['width: ' . ($room->capacity > 0 ? min(100, $v->occ / $room->capacity * 100) : 0) . '%'])></span></div>
                            </div>
                            <div class="rm-card-body">
                                @forelse ($room->beds as $bed)
                                    @php
                                        $assignment = $bed->currentAssignment;
                                        $tenant = $assignment?->tenant;
                                        $cls = in_array($bed->status, ['available', 'occupied']) ? $bed->status : 'other';
                                    @endphp
                                    <div class="rm-bed">
                                        <div class="rm-bed-label">{{ $bed->bed_label }}</div>
                                        <div class="rm-bed-who">
                                            @if ($tenant)
                                                <span class="rm-avatar" aria-hidden="true">{{ $initials($tenant->user->name) }}</span>
                                                <span class="n">{{ $tenant->user->name }}</span>
                                            @elseif ($bed->status === 'available')
                                                <span class="rm-avatar is-free" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                                                <span class="rm-bed-free">Vacant</span>
                                            @else
                                                <span class="rm-avatar is-other" aria-hidden="true"><i class="bi bi-tools"></i></span>
                                                <span class="rm-pill b-{{ $cls }}">{{ ucfirst($bed->status) }}</span>
                                            @endif
                                        </div>
                                        @if ($bed->status === 'available')
                                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#assignBedModal{{ $bed->id }}"><i class="bi bi-person-plus me-1"></i>Quick assign</button>
                                        @elseif ($assignment)
                                            <form method="POST" action="{{ route('proprietor.room-assignments.update', $assignment) }}" onsubmit="return confirm('End this tenant assignment?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="End assignment"><i class="bi bi-box-arrow-right me-1"></i>End</button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rm-meta py-3">No beds found for this room.</div>
                                @endforelse
                            </div>
                            <div class="rm-card-foot">
                                <a href="{{ route('proprietor.rooms.edit', $room) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                                <form method="POST" action="{{ route('proprietor.rooms.destroy', $room) }}" onsubmit="return confirm('Delete this room and its beds?');" class="ms-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                                </form>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            {{-- Data table --}}
            <div id="rmTable" class="rm-tablewrap" hidden>
                <div class="table-responsive">
                    <table class="table rm-table" id="rmTableEl">
                        <thead>
                            <tr>
                                <th scope="col">Room</th>
                                <th scope="col">Floor</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Occupied beds</th>
                                <th scope="col">Monthly rate</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end" data-noexport>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vm as $v)
                                @php $room = $v->room; @endphp
                                <tr class="rm-item" data-floor="{{ $room->floor }}" data-state="{{ $v->state }}" data-type="{{ Str::lower((string) $v->type) }}" data-search="{{ $v->search }}">
                                    <td class="fw-semibold">Room {{ $room->room_number }}</td>
                                    <td>{{ $room->floor }}</td>
                                    <td>{{ $room->capacity }}</td>
                                    <td>{{ $v->occ }} / {{ $room->capacity }}</td>
                                    <td>PHP {{ number_format((float) $room->monthly_rate, 2) }}</td>
                                    <td><span class="rm-pill st-{{ $v->state }}">{{ $stateLabels[$v->state] }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('proprietor.rooms.edit', $room) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-goto="room-{{ $room->id }}"><i class="bi bi-people me-1"></i>Manage tenants</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rm-empty mt-3" id="rmNoMatch" hidden><i class="bi bi-search" aria-hidden="true"></i>No rooms match these filters. Try clearing one.</div>
        @endif
    </div>

    {{-- Assign modals --}}
    @foreach ($vm as $v)
        @foreach ($v->room->beds->where('status', 'available') as $bed)
            <div class="modal fade rm" id="assignBedModal{{ $bed->id }}" tabindex="-1" aria-labelledby="assignBedModalLabel{{ $bed->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('proprietor.room-assignments.store') }}">
                            @csrf
                            <input type="hidden" name="bed_id" value="{{ $bed->id }}">
                            <div class="modal-header">
                                <h2 class="modal-title h5 fw-semibold" id="assignBedModalLabel{{ $bed->id }}">Assign {{ $bed->bed_label }} in Room {{ $v->room->room_number }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @if ($availableTenants->isEmpty())
                                    <div class="alert alert-warning mb-0">No tenants are available to assign right now. Add a tenant, or end another tenant's bed assignment, then try again.</div>
                                @else
                                    <label for="tenant_id_{{ $bed->id }}" class="form-label">Tenant</label>
                                    <select class="form-select" id="tenant_id_{{ $bed->id }}" name="tenant_id" required>
                                        <option value="">Select tenant</option>
                                        @foreach ($availableTenants as $tenant)
                                            <option value="{{ $tenant->id }}">{{ $tenant->user->name }} - {{ $tenant->user->email }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" @disabled($availableTenants->isEmpty())>Assign tenant</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach

    <script>
        (function () {
            var root = document.getElementById('rm');
            if (!root) return;
            var $ = function (id) { return document.getElementById(id); };
            var grid = $('rmGrid'), table = $('rmTable'), noMatch = $('rmNoMatch');
            if (!grid) return;
            var items = root.querySelectorAll('.rm-item');
            var rooms = grid.querySelectorAll('.rm-item').length;

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

            var f = { q: $('rmSearch'), floor: $('rmFloor'), state: $('rmState'), type: $('rmType') };
            function apply() {
                var q = f.q.value.trim().toLowerCase(), shown = 0;
                items.forEach(function (el) {
                    var ok = (!q || el.dataset.search.indexOf(q) > -1)
                        && (!f.floor.value || el.dataset.floor === f.floor.value)
                        && (!f.state.value || el.dataset.state === f.state.value)
                        && (!f.type || !f.type.value || el.dataset.type === f.type.value);
                    el.hidden = !ok;
                    if (ok && el.closest('#rmGrid')) shown++;
                });
                $('rmCount').textContent = 'Showing ' + shown + ' of ' + rooms + ' rooms';
                noMatch.hidden = shown !== 0;
            }
            [f.q, f.floor, f.state, f.type].forEach(function (el) {
                if (el) { el.addEventListener('input', apply); el.addEventListener('change', apply); }
            });
            $('rmReset').addEventListener('click', function () {
                f.q.value = ''; f.floor.value = ''; f.state.value = '';
                if (f.type) f.type.value = '';
                apply();
            });

            // Put the cursor in the tenant list when an assign modal opens
            document.addEventListener('shown.bs.modal', function (e) {
                var sel = e.target.querySelector('select');
                if (sel) sel.focus();
            });

            // "Manage tenants": jump to the room card and highlight it
            root.querySelectorAll('[data-goto]').forEach(function (b) {
                b.addEventListener('click', function () {
                    setView('grid');
                    var card = $(b.dataset.goto);
                    if (!card) return;
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    var inner = card.querySelector('.rm-card');
                    inner.classList.add('is-flash');
                    setTimeout(function () { inner.classList.remove('is-flash'); }, 1800);
                });
            });

            // Export the rooms currently visible in the filters as CSV
            $('rmExport').addEventListener('click', function () {
                var cols = [], head = [];
                document.querySelectorAll('#rmTableEl thead th').forEach(function (th, i) {
                    if (!th.hasAttribute('data-noexport')) { cols.push(i); head.push(th.textContent.trim()); }
                });
                var esc = function (s) { return '"' + String(s).replace(/"/g, '""') + '"'; };
                var lines = [head.map(esc).join(',')];
                document.querySelectorAll('#rmTableEl tbody tr').forEach(function (tr) {
                    if (tr.hidden) return;
                    var tds = tr.querySelectorAll('td');
                    lines.push(cols.map(function (i) { return esc(tds[i].textContent.replace(/\s+/g, ' ').trim()); }).join(','));
                });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv' }));
                a.download = 'rooms-' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
                URL.revokeObjectURL(a.href);
            });
        })();
    </script>
@endsection
