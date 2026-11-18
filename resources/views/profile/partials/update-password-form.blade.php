<form id="update-password-form" method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-3">
        <x-password-input
            id="update_password_current_password"
            name="current_password"
            label="Current Password"
            autocomplete="current-password"
            error-bag="updatePassword"
            :toggle="$showPasswordToggle ?? false"
        />
    </div>

    <div class="mb-3">
        <x-password-input
            id="update_password_password"
            name="password"
            label="New Password"
            autocomplete="new-password"
            error-bag="updatePassword"
            :description="isset($passwordRequirements) ? 'tenant-password-requirements' : null"
            :toggle="$showPasswordToggle ?? false"
        />
        @isset($passwordRequirements)
            <ul id="tenant-password-requirements" class="list-unstyled small text-body-secondary mt-2 mb-0" aria-label="New password requirements">
                @foreach ($passwordRequirements as $requirement)
                    <li data-pattern="{{ $requirement['pattern'] }}">
                        <i class="bi bi-circle me-1" aria-hidden="true"></i>
                        <span class="visually-hidden" data-requirement-status></span>{{ $requirement['label'] }}
                    </li>
                @endforeach
            </ul>
        @endisset
    </div>

    <div class="mb-3">
        <x-password-input
            id="update_password_password_confirmation"
            name="password_confirmation"
            label="Confirm Password"
            autocomplete="new-password"
            error-bag="updatePassword"
            :toggle="$showPasswordToggle ?? false"
        />
    </div>

    <div class="d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Save</button>
        @if (session('status') === 'password-updated')
            <span class="text-success small">Saved.</span>
        @endif
    </div>
</form>