@csrf

<div class="row g-3">
    <div class="col-md-4">
        <label for="first_name" class="form-label">First Name</label>
        <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $tenant->user->first_name ?? '') }}" required>
        @error('first_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="middle_name" class="form-label">Middle Name <span class="text-muted">(Optional)</span></label>
        <input type="text" class="form-control @error('middle_name') is-invalid @enderror" id="middle_name" name="middle_name" value="{{ old('middle_name', $tenant->user->middle_name ?? '') }}">
        @error('middle_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="last_name" class="form-label">Last Name</label>
        <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $tenant->user->last_name ?? '') }}" required>
        @error('last_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $tenant->user->email ?? '') }}" required>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="contact_number" class="form-label">Contact Number</label>
        <input type="text" class="form-control @error('contact_number') is-invalid @enderror" id="contact_number" name="contact_number" value="{{ old('contact_number',$tenant->user->contact_number ?? '') }}">
        @error('contact_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>



    <div class="col-md-6">
        <label for="bed_id" class="form-label">Room / Bed <span class="text-muted">(Optional)</span></label>
        <select class="form-select @error('bed_id') is-invalid @enderror" id="bed_id" name="bed_id">
            <option value="">
                {{ isset($tenant) ? '— Remove from Room —' : '— Assign later —' }}
            </option>
            @foreach ($availableBeds ?? [] as $bed)
                <option value="{{ $bed->id }}"
                    @selected(old('bed_id', $tenant->currentAssignment->bed_id ?? '') == $bed->id)>
                    Room {{ $bed->room->room_number }} — {{ $bed->bed_label }}
                    @if (isset($tenant) && $tenant->currentAssignment?->bed_id === $bed->id)
                        (current)
                    @endif
                </option>
            @endforeach
        </select>
        @error('bed_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @if (isset($tenant) && ! $tenant->currentAssignment)
            <div class="form-text text-muted">This tenant is not currently assigned to a room.</div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
        <input type="text" class="form-control @error('emergency_contact_name') is-invalid @enderror" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $tenant->emergency_contact_name ?? '') }}" required>
        @error('emergency_contact_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
        <input type="text" class="form-control @error('emergency_contact_number') is-invalid @enderror" id="emergency_contact_number" name="emergency_contact_number" value="{{ old('emergency_contact_number', $tenant->emergency_contact_number ?? '') }}" required>
        @error('emergency_contact_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="alert alert-info mb-0">
            Guardian details are optional. Collect and encode them only when the tenant or guardian has given consent.
        </div>
    </div>

    <div class="col-md-6">
        <label for="guardian_name" class="form-label">Guardian Name <span class="text-muted">(Optional)</span></label>
        <input type="text" class="form-control @error('guardian_name') is-invalid @enderror" id="guardian_name"name="guardian_name" value="{{ old('guardian_name', $tenant->guardian_name ?? '') }}">
        @error('guardian_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="guardian_contact_number" class="form-label">Guardian Number <span class="text-muted">(Optional)</span></label>
        <input type="text" class="form-control @error('guardian_contact_number') is-invalid @enderror" id="guardian_contact_number" name="guardian_contact_number" value="{{ old('guardian_contact_number', $tenant->guardian_contact_number ?? '') }}">
        @error('guardian_contact_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="move_in_date" class="form-label">Move-in Date</label>
        <input type="date" class="form-control @error('move_in_date') is-invalid @enderror" id="move_in_date" name="move_in_date" value="{{ old('move_in_date', isset($tenant) && $tenant->move_in_date ? $tenant->move_in_date->format('Y-m-d') : '') }}" required>
        @error('move_in_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
            @foreach (['active' => 'Active', 'inactive'=> 'Inactive'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $tenant->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
</div>
