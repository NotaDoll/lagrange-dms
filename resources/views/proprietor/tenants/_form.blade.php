@csrf

@php
    $tenant = $tenant ?? null;
    $editing = $tenant !== null;
    $currentBed = $tenant?->currentAssignment?->bed;
    $currentBedId = $currentBed?->id;
    $currentRoom = $currentBed?->room;
    $selectedType = old('room_type', $currentRoom ? ($currentRoom->catalogType() ?? \App\Services\RoomAvailability::LEGACY_TYPE) : '');
    $selectedRoomId = old('room_id', $currentRoom?->id);
    $selectedBedId = old('bed_id', $currentBedId);
    $hasAvailableBeds = collect($roomAvailability)->contains('selectable', true);
    $bedRequired = ! $editing || $currentBedId !== null;
    $statuses = \App\Models\Tenant::STATUS_LABELS;
    if (! $editing) {
        $statuses = array_intersect_key($statuses, array_flip([\App\Models\Tenant::STATUS_RESERVED, \App\Models\Tenant::STATUS_ACTIVE]));
    }
    $today = \App\Models\Tenant::localDate();
    if ($editing && ! $tenant->canBeReserved()) {
        unset($statuses[\App\Models\Tenant::STATUS_RESERVED]);
    }
    $selectedStatus = old('status', $tenant?->status ?? '');
    if (! is_string($selectedStatus) || ! isset($statuses[$selectedStatus])) {
        $selectedStatus = $tenant?->status ?? '';
    }
    $recordedMoveIn = $tenant?->actualMoveInDate()?->toDateString();
    $active = $selectedStatus === \App\Models\Tenant::STATUS_ACTIVE;
    $dateDisabled = $selectedStatus === \App\Models\Tenant::STATUS_RESERVED || (! $active && ! $recordedMoveIn);
    $moveInValue = old('move_in_date', $recordedMoveIn ?? ($active ? $today : ''));
    $dateHelp = $dateDisabled
        ? 'Set when the tenant moves in.'
        : 'Billing starts on the move-in date.';
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="first_name" class="form-label">First Name</label>
        <input type="text" id="first_name" name="first_name" class="form-control text-uppercase @error('first_name') is-invalid @enderror" maxlength="255" autocomplete="given-name" required
            value="{{ old('first_name', $tenant?->first_name ?? $tenant?->user?->first_name ?? '') }}" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
        @error('first_name') <div class="invalid-feedback" id="first_name-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="middle_name" class="form-label">Middle Name <span class="text-muted">(Optional)</span></label>
        <input type="text" id="middle_name" name="middle_name" class="form-control text-uppercase @error('middle_name') is-invalid @enderror" maxlength="255" autocomplete="additional-name"
            value="{{ old('middle_name', $tenant?->middle_name ?? $tenant?->user?->middle_name ?? '') }}" @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror>
        @error('middle_name') <div class="invalid-feedback" id="middle_name-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="last_name" class="form-label">Last Name</label>
        <input type="text" id="last_name" name="last_name" class="form-control text-uppercase @error('last_name') is-invalid @enderror" maxlength="255" autocomplete="family-name" required
            value="{{ old('last_name', $tenant?->last_name ?? $tenant?->user?->last_name ?? '') }}" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
        @error('last_name') <div class="invalid-feedback" id="last_name-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" required
            value="{{ old('email', $tenant?->email ?? '') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email') <div class="invalid-feedback" id="email-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="contact_number" class="form-label">Contact Number</label>
        <input type="tel" id="contact_number" name="contact_number" class="form-control @error('contact_number') is-invalid @enderror" maxlength="50" autocomplete="tel" @required(! $editing)
            value="{{ old('contact_number', $tenant?->contact_number ?? '') }}" @error('contact_number') aria-invalid="true" aria-describedby="contact_number-error" @enderror>
        @error('contact_number') <div class="invalid-feedback" id="contact_number-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="home_address" class="form-label">Home Address</label>
        <textarea id="home_address" name="home_address" class="form-control @error('home_address') is-invalid @enderror" rows="2" maxlength="5000" autocomplete="street-address" @required(! $editing)
            @error('home_address') aria-invalid="true" aria-describedby="home_address-error" @enderror>{{ old('home_address', ($tenant?->home_address ?? '')) }}</textarea>
        @error('home_address') <div class="invalid-feedback" id="home_address-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="room_type" class="form-label">Room Type</label>
                <select id="room_type" name="room_type" class="form-select @error('room_type') is-invalid @enderror" @required($bedRequired)
                    aria-describedby="room-selection-help @error('room_type') room_type-error @enderror" @error('room_type') aria-invalid="true" @enderror>
                    <option value="">Select room type</option>
                    @foreach ($roomAvailability as $key => $type)
                        @php
                            $rates = array_unique($type['rates']);
                            $label = implode('–', array_map(fn ($rate) => '₱'.number_format($rate, $rate == floor($rate) ? 0 : 2), $rates));
                        @endphp
                        <option value="{{ $key }}" @selected($selectedType === $key) @disabled(! $type['selectable'])>
                            {{ $type['label'] }} ({{ $label }}/mo){{ $type['selectable'] ? '' : ' (Full)' }}
                        </option>
                    @endforeach
                </select>
                @error('room_type') <div class="invalid-feedback" id="room_type-error">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="room_id" class="form-label">Room Number</label>
                <select id="room_id" name="room_id" class="form-select @error('room_id') is-invalid @enderror" disabled
                    aria-describedby="room-selection-help @error('room_id') room_id-error @enderror" @error('room_id') aria-invalid="true" @enderror>
                    <option value="">Select room number</option>
                </select>
                @error('room_id') <div class="invalid-feedback" id="room_id-error">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="bed_id" class="form-label">Bed</label>
                <select id="bed_id" name="bed_id" class="form-select @error('bed_id') is-invalid @enderror" disabled
                    aria-describedby="room-selection-help @error('bed_id') bed_id-error @enderror" @error('bed_id') aria-invalid="true" @enderror>
                    <option value="">Select bed</option>
                </select>
                @error('bed_id') <div class="invalid-feedback" id="bed_id-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="form-text" id="room-selection-help" aria-live="polite">
            @if (! $hasAvailableBeds)
                No available beds.
            @elseif ($editing && ! $currentBedId)
                This tenant is not currently assigned to a room.
            @endif
        </div>
        <div class="form-text" id="room-monthly-rent" aria-live="polite"></div>
    </div>

    <div class="col-md-3">
        <label for="move_in_date" class="form-label">Move-in Date</label>
        <input type="date" id="move_in_date" name="move_in_date" class="form-control @error('move_in_date') is-invalid @enderror" @required($active) @disabled($dateDisabled) max="{{ $today }}"
            value="{{ ! $dateDisabled && is_string($moveInValue) ? $moveInValue : '' }}" aria-describedby="move_in_date-help @error('move_in_date') move_in_date-error @enderror" @error('move_in_date') aria-invalid="true" @enderror>
        <div id="move_in_date-help" class="form-text">{{ $dateHelp }}</div>
        @error('move_in_date') <div class="invalid-feedback" id="move_in_date-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="status" class="form-label">Status</label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required
            @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
            <option value="" disabled @selected($selectedStatus === '')>Select status</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback" id="status-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="security_deposit" class="form-label">Security Deposit <span class="text-muted">(Optional)</span></label>
        <input type="number" id="security_deposit" name="security_deposit" class="form-control @error('security_deposit') is-invalid @enderror" min="0" max="99999999.99" step="0.01" inputmode="decimal"
            value="{{ old('security_deposit', $tenant?->security_deposit ?? '0.00') }}" aria-describedby="security_deposit-help @error('security_deposit') security_deposit-error @enderror" @error('security_deposit') aria-invalid="true" @enderror>
        <div id="security_deposit-help" class="form-text">Amount in Philippine pesos.</div>
        @error('security_deposit') <div class="invalid-feedback" id="security_deposit-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="advance_payment" class="form-label">Advance Payment <span class="text-muted">(Optional)</span></label>
        <input type="number" id="advance_payment" name="advance_payment" class="form-control @error('advance_payment') is-invalid @enderror" min="0" max="99999999.99" step="0.01" inputmode="decimal"
            value="{{ old('advance_payment', $tenant?->advance_payment ?? '0.00') }}" aria-describedby="advance_payment-help @error('advance_payment') advance_payment-error @enderror" @error('advance_payment') aria-invalid="true" @enderror>
        <div id="advance_payment-help" class="form-text">Automatically applied to rent, starting with the oldest unpaid charge.</div>
        @error('advance_payment') <div class="invalid-feedback" id="advance_payment-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="opening_balance" class="form-label">Opening balance (optional)</label>
        <input type="number" min="0" max="99999999.99" step="0.01" id="opening_balance" name="opening_balance"
               class="form-control @error('opening_balance') is-invalid @enderror"
               value="{{ old('opening_balance', $tenant->opening_balance ?? '') }}"
               aria-describedby="opening-balance-help @error('opening_balance') opening-balance-error @enderror">
        <div class="form-text" id="opening-balance-help">Amount owed in pesos as of the billing start date.</div>
        @error('opening_balance') <div class="invalid-feedback" id="opening-balance-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control text-uppercase @error('emergency_contact_name') is-invalid @enderror" maxlength="255" @required(! $editing)
            value="{{ old('emergency_contact_name', ($tenant?->emergency_contact_name ?? '')) }}" @error('emergency_contact_name') aria-invalid="true" aria-describedby="emergency_contact_name-error" @enderror>
        @error('emergency_contact_name') <div class="invalid-feedback" id="emergency_contact_name-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control @error('emergency_contact_number') is-invalid @enderror" maxlength="50" @required(! $editing)
            value="{{ old('emergency_contact_number', ($tenant?->emergency_contact_number ?? '')) }}" @error('emergency_contact_number') aria-invalid="true" aria-describedby="emergency_contact_number-error" @enderror>
        @error('emergency_contact_number') <div class="invalid-feedback" id="emergency_contact_number-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="emergency_contact_relationship" class="form-label">Relationship</label>
        <select class="form-select @error('emergency_contact_relationship') is-invalid @enderror"
            id="emergency_contact_relationship" name="emergency_contact_relationship" @required(! $editing)
            @error('emergency_contact_relationship') aria-invalid="true" aria-describedby="emergency_contact_relationship-error" @enderror>
            <option value="" @disabled(! $editing) @selected(! old('emergency_contact_relationship', $tenant?->emergency_contact_relationship))>Select relationship</option>
            @foreach (\App\Models\Tenant::EMERGENCY_RELATIONSHIPS as $value => $label)
                <option value="{{ $value }}" @selected(old('emergency_contact_relationship', $tenant?->emergency_contact_relationship) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('emergency_contact_relationship')
            <div class="invalid-feedback" id="emergency_contact_relationship-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="alert alert-info mb-0">
            Guardian details are optional. Collect and encode them only when the tenant or guardian has given consent.
        </div>
    </div>

    <div class="col-md-6">
        <label for="guardian_name" class="form-label">Guardian Name <span class="text-muted">(Optional)</span></label>
        <input type="text" id="guardian_name" name="guardian_name" class="form-control text-uppercase @error('guardian_name') is-invalid @enderror" maxlength="255"
            value="{{ old('guardian_name', ($tenant?->guardian_name ?? '')) }}" @error('guardian_name') aria-invalid="true" aria-describedby="guardian_name-error" @enderror>
        @error('guardian_name') <div class="invalid-feedback" id="guardian_name-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="guardian_contact_number" class="form-label">Guardian Number <span class="text-muted">(Optional)</span></label>
        <input type="tel" id="guardian_contact_number" name="guardian_contact_number" class="form-control @error('guardian_contact_number') is-invalid @enderror" maxlength="50"
            value="{{ old('guardian_contact_number', ($tenant?->guardian_contact_number ?? '')) }}" @error('guardian_contact_number') aria-invalid="true" aria-describedby="guardian_contact_number-error" @enderror>
        @error('guardian_contact_number') <div class="invalid-feedback" id="guardian_contact_number-error">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check small">
            <input type="checkbox" class="form-check-input" id="same_as_emergency_contact">
            <label class="form-check-label" for="same_as_emergency_contact">Same as emergency contact</label>
        </div>
    </div>

    <div class="col-12">
        @if ($tenant?->data_privacy_consent_at)
            <div class="form-text">Data privacy consent recorded {{ $tenant->data_privacy_consent_at->copy()->timezone(\App\Models\Tenant::LOCAL_TIMEZONE)->format('M d, Y, g:i A') }}.</div>
        @else
            <div class="form-check">
                <input type="checkbox" class="form-check-input @error('data_privacy_consent') is-invalid @enderror"
                    id="data_privacy_consent" name="data_privacy_consent" value="1" @required(! $editing)
                    @checked(old('data_privacy_consent')) @error('data_privacy_consent') aria-invalid="true" aria-describedby="data_privacy_consent-error" @enderror>
                <label class="form-check-label" for="data_privacy_consent">
                    The tenant (or guardian) has given consent to the collection and processing of this personal information (Data Privacy Act).
                </label>
                @error('data_privacy_consent') <div class="invalid-feedback" id="data_privacy_consent-error">{{ $message }}</div> @enderror
            </div>
        @endif
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
    <button type="submit" class="btn btn-primary" @disabled(! $editing && ! $hasAvailableBeds)><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $submitLabel }}</button>
</div>

@push('scripts')
<script>
(() => {
    const get = id => document.getElementById(id);
    const availability = @js($roomAvailability);
    const typeSelect = get('room_type');
    const roomSelect = get('room_id');
    const bedSelect = get('bed_id');
    const rentText = get('room-monthly-rent');
    const bedRequired = @js($bedRequired);
    const money = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 });

    function renderBeds(selectedBed = '') {
        const type = availability[typeSelect.value];
        const room = type?.rooms.find(item => String(item.id) === roomSelect.value);

        bedSelect.replaceChildren(new Option('Select bed', ''));
        bedSelect.disabled = !room || room.beds.length === 0;
        bedSelect.required = bedRequired || Boolean(typeSelect.value);
        room?.beds.forEach(bed => {
            bedSelect.add(new Option(bed.label + (bed.current ? ' (current)' : ''), bed.id, false, String(bed.id) === String(selectedBed)));
        });

        const rates = room ? [room.rate] : [...new Set(type?.rates ?? [])];
        rentText.textContent = rates.length
            ? 'Monthly rent: ' + rates.map(rate => '₱' + money.format(rate)).join('–')
            : '';
    }

    function renderRooms(selectedRoom = '', selectedBed = '') {
        const type = availability[typeSelect.value];

        roomSelect.replaceChildren(new Option('Select room number', ''));
        roomSelect.disabled = !type || type.rooms.length === 0;
        roomSelect.required = bedRequired || Boolean(typeSelect.value);
        type?.rooms.forEach(room => {
            const availabilityLabel = room.free ? room.free + (room.free === 1 ? ' bed free' : ' beds free')
                : (room.beds.length ? 'Full, current room' : 'Full');
            const option = new Option('Room ' + room.number + ' (' + availabilityLabel + ')', room.id, false, String(room.id) === String(selectedRoom));
            option.disabled = room.beds.length === 0;
            roomSelect.add(option);
        });

        renderBeds(selectedBed);
    }

    typeSelect.addEventListener('change', () => renderRooms());
    roomSelect.addEventListener('change', () => renderBeds());
    renderRooms(@js($selectedRoomId), @js($selectedBedId));

    const sameAsEmergency = get('same_as_emergency_contact');
    const emergencyName = get('emergency_contact_name');
    const emergencyNumber = get('emergency_contact_number');
    const guardianName = get('guardian_name');
    const guardianNumber = get('guardian_contact_number');
    function copyEmergencyContact() {
        if (sameAsEmergency.checked) {
            guardianName.value = emergencyName.value;
            guardianNumber.value = emergencyNumber.value;
        }
    }

    sameAsEmergency.addEventListener('change', copyEmergencyContact);
    [emergencyName, emergencyNumber].forEach(field => field.addEventListener('input', copyEmergencyContact));
    [guardianName, guardianNumber].forEach(field => field.addEventListener('input', () => {
        sameAsEmergency.checked = false;
    }));

    const moveIn = get('move_in_date');
    const status = get('status');
    const dateHelp = get('move_in_date-help');
    const today = @js($today);
    const recordedMoveIn = @js($recordedMoveIn);
    function syncMoveIn() {
        const active = status.value === 'active';
        const disabled = status.value === 'reserved' || (!active && !recordedMoveIn);
        moveIn.disabled = disabled;
        moveIn.required = active;

        if (disabled) {
            moveIn.value = '';
        } else if (!moveIn.value) {
            moveIn.value = recordedMoveIn || (active ? today : '');
        }

        dateHelp.textContent = disabled ? 'Set when the tenant moves in.' : 'Billing starts on the move-in date.';
    }

    status.addEventListener('change', syncMoveIn);
    syncMoveIn();
})();
</script>
@endpush
