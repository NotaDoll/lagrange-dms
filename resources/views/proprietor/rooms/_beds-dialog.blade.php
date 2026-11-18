@php
    $room = $v->room;
    $availableBeds = $room->beds->where('status', 'available');
@endphp

<div class="modal fade rm rm-dialog" id="roomBeds{{ $room->id }}" tabindex="-1" aria-labelledby="roomBedsLabel{{ $room->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 fw-semibold mb-1" id="roomBedsLabel{{ $room->id }}">{{ $room->displayName() }} beds</h2>
                    <p class="rm-meta mb-0">{{ $v->occ }} of {{ $room->capacity }} occupied · {{ $v->vac }} available</p>
                </div>
                <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if ($availableBeds->isNotEmpty())
                    @if ($availableTenants->isEmpty())
                        <p class="rm-meta mb-2">No unassigned active tenants are available.</p>
                    @else
                        <details class="rm-assign" id="roomAssign{{ $room->id }}">
                            <summary>Assign a bed</summary>
                            <form method="POST" action="{{ route('proprietor.room-assignments.store') }}">
                                @csrf
                                <div class="rm-assign-fields">
                                    <div>
                                        <label class="form-label" for="roomAssignBed{{ $room->id }}">Available bed</label>
                                        <select class="form-select" id="roomAssignBed{{ $room->id }}" name="bed_id" required>
                                            <option value="">Select a bed</option>
                                            @foreach ($availableBeds as $bed)
                                                <option value="{{ $bed->id }}">{{ $bed->displayLabel() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="roomAssignTenant{{ $room->id }}">Tenant</label>
                                        <select class="form-select" id="roomAssignTenant{{ $room->id }}" name="tenant_id" required data-searchable data-placeholder="Search tenant by name or email">
                                            <option value="">Select a tenant</option>
                                            @foreach ($availableTenants as $tenant)
                                                <option value="{{ $tenant->id }}">{{ $tenant->name }} - {{ $tenant->email }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-person-plus" aria-hidden="true"></i> Assign tenant</button>
                            </form>
                        </details>
                    @endif
                @endif

                @if ($room->beds->isEmpty())
                    <p class="rm-meta mb-0">This room has no beds.</p>
                @else
                    <ul class="rm-bed-list">
                        @foreach ($room->beds as $bed)
                            @php
                                $assignment = $bed->currentAssignment;
                                $status = $bed->status;
                            @endphp
                            <li class="rm-bed">
                                <div class="rm-bed-info">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h3 class="rm-bed-label">{{ $bed->displayLabel() }}</h3>
                                        @if ($assignment)
                                            <span class="rm-pill b-occupied">Occupied</span>
                                        @elseif ($status === 'available')
                                            <span class="rm-pill b-available">Available</span>
                                        @else
                                            <span class="rm-pill b-other">Maintenance</span>
                                        @endif
                                    </div>
                                    @if ($assignment?->tenant)
                                        <p class="rm-bed-who">{{ $assignment->tenant->name }}</p>
                                    @endif
                                </div>
                                <div class="rm-bed-actions">
                                    @if ($assignment)
                                        <form method="POST" action="{{ route('proprietor.room-assignments.update', $assignment) }}" onsubmit="return confirm('End this tenant assignment?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" aria-label="End assignment for {{ $bed->locationLabel() }}"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> End assignment</button>
                                        </form>
                                    @else
                                        @if ($status === 'available' && $availableTenants->isNotEmpty())
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-assign-bed="{{ $bed->id }}" aria-controls="roomAssign{{ $room->id }}" aria-label="Assign {{ $bed->locationLabel() }}">Assign</button>
                                        @endif
                                        <form method="POST" action="{{ route('proprietor.beds.status.update', $bed) }}" class="rm-bed-status">
                                            @csrf
                                            @method('PATCH')
                                            <select class="form-select form-select-sm" name="status" aria-label="Status for {{ $bed->locationLabel() }}">
                                                <option value="available" @selected($status === 'available')>Available</option>
                                                <option value="maintenance" @selected($status === 'maintenance')>Maintenance</option>
                                            </select>
                                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check-lg" aria-hidden="true"></i> Save status</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="modal-footer justify-content-between">
                <form method="POST" action="{{ route('proprietor.rooms.destroy', $room) }}" onsubmit="return confirm('Delete this room and its beds?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete room</button>
                </form>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>
            </div>
        </div>
    </div>
</div>
