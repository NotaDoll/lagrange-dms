@extends('layouts.proprietor')

@section('title', 'Rooms')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Rooms</h1>
        <a href="{{ route('proprietor.rooms.create') }}" class="btn btn-primary">Add Room</a>
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

    <div class="row g-3">
        @forelse ($rooms as $room)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="h5 mb-1">Room {{ $room->room_number }}</h2>
                            <div class="text-muted small">Floor {{ $room->floor }} / PHP {{ number_format((float) $room->monthly_rate, 2) }} per month</div>
                        </div>
                        <span class="badge text-bg-light">{{ $room->beds->where('status', 'occupied')->count() }}/{{ $room->capacity }}</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="text-muted small">{{ $room->capacity }} bed capacity</div>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Room actions">
                                <a href="{{ route('proprietor.rooms.edit', $room) }}" class="btn btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('proprietor.rooms.destroy', $room) }}" onsubmit="return confirm('Delete this room and its beds?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </div>

                        <div class="list-group list-group-flush">
                            @forelse ($room->beds as $bed)
                                @php
                                    $assignment = $bed->currentAssignment;
                                    $tenant = $assignment?->tenant;
                                @endphp
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold">{{ $bed->bed_label }}</div>
                                            @if ($tenant)
                                                <div class="text-muted small">{{ $tenant->user->name }}</div>
                                            @else
                                                <div class="text-muted small">No active tenant</div>
                                            @endif
                                        </div>
                                        <span class="badge {{ $bed->status === 'available' ? 'text-bg-success' : 'text-bg-danger' }}">
                                            {{ ucfirst($bed->status) }}
                                        </span>
                                    </div>

                                    <div class="mt-2">
                                        @if ($bed->status === 'available')
                                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#assignBedModal{{ $bed->id }}" @disabled($availableTenants->isEmpty())>
                                                Assign Tenant
                                            </button>
                                        @elseif ($assignment)
                                            <form method="POST" action="{{ route('proprietor.room-assignments.update', $assignment) }}" onsubmit="return confirm('End this tenant assignment?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">End Assignment</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">No beds found for this room.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">No rooms found.</div>
                </div>
            </div>
        @endforelse
    </div>

    @foreach ($rooms as $room)
        @foreach ($room->beds->where('status', 'available') as $bed)
            <div class="modal fade" id="assignBedModal{{ $bed->id }}" tabindex="-1" aria-labelledby="assignBedModalLabel{{ $bed->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('proprietor.room-assignments.store') }}">
                            @csrf
                            <input type="hidden" name="bed_id" value="{{ $bed->id }}">
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="assignBedModalLabel{{ $bed->id }}">Assign {{ $bed->bed_label }} in Room {{ $room->room_number }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @if ($availableTenants->isEmpty())
                                    <div class="alert alert-warning mb-0">No tenants are available for assignment.</div>
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
                                <button type="submit" class="btn btn-primary" @disabled($availableTenants->isEmpty())>Assign Tenant</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach
@endsection
