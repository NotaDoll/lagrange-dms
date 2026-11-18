@csrf

@php($addingRoom = !isset($room))

<div class="row g-3">
    @if ($addingRoom)
        <div class="col-sm-6 col-lg-3">
            <label for="type" class="form-label">Room Type</label>
            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" data-add-type="{{ $addType }}" required>
                <option value="">Select room type</option>
                @foreach ($roomTypes as $roomType)
                    <option value="{{ $roomType }}" @selected(old('type') === $roomType)>{{ $roomType }}</option>
                @endforeach
                <option value="{{ $addType }}" @selected(old('type') === $addType)>Add room type</option>
            </select>
            @error('type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
    <div class="{{ $addingRoom ? 'col-sm-6 col-lg-3' : 'col-md-4' }}">
        <label for="room_number" class="form-label">Room Number</label>
        <input type="text" class="form-control text-uppercase @error('room_number') is-invalid @enderror" id="room_number" name="room_number" value="{{ old('room_number', $room->room_number ?? '') }}" required>
        @error('room_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="{{ $addingRoom ? 'col-sm-6 col-lg-3' : 'col-md-4' }}">
        <label for="capacity" class="form-label">Capacity</label>
        <input type="number" min="1" max="50" class="form-control @error('capacity') is-invalid @enderror" id="capacity" name="capacity" value="{{ old('capacity', $room->capacity ?? 1) }}" required>
        @error('capacity')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="{{ $addingRoom ? 'col-sm-6 col-lg-3' : 'col-md-4' }}">
        <label for="monthly_rate" class="form-label">Monthly Rate</label>
        <input type="number" min="0" step="0.01" class="form-control @error('monthly_rate') is-invalid @enderror" id="monthly_rate" name="monthly_rate" value="{{ old('monthly_rate', $room->monthly_rate ?? '') }}" required>
        @error('monthly_rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @if ($addingRoom)
        <div class="col-sm-6 col-lg-3" id="new-type-field">
            <label for="new_type" class="form-label">New Room Type</label>
            <input type="text" class="form-control text-uppercase @error('new_type') is-invalid @enderror" id="new_type" name="new_type" maxlength="20" value="{{ old('new_type') }}">
            @error('new_type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('proprietor.rooms.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $submitLabel }}</button>
</div>
