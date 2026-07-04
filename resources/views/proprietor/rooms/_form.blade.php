@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="room_number" class="form-label">Room Number</label>
        <input type="text" class="form-control @error('room_number') is-invalid @enderror" id="room_number" name="room_number" value="{{ old('room_number', $room->room_number ?? '') }}" required>
        @error('room_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="floor" class="form-label">Floor</label>
        <input type="text" class="form-control @error('floor') is-invalid @enderror" id="floor" name="floor" value="{{ old('floor', $room->floor ?? '') }}" required>
        @error('floor')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="capacity" class="form-label">Capacity</label>
        <input type="number" min="1" max="50" class="form-control @error('capacity') is-invalid @enderror" id="capacity" name="capacity" value="{{ old('capacity', $room->capacity ?? 1) }}" required>
        @error('capacity')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="monthly_rate" class="form-label">Monthly Rate</label>
        <input type="number" min="0" step="0.01" class="form-control @error('monthly_rate') is-invalid @enderror" id="monthly_rate" name="monthly_rate" value="{{ old('monthly_rate', $room->monthly_rate ?? '') }}" required>
        @error('monthly_rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('proprietor.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
</div>
