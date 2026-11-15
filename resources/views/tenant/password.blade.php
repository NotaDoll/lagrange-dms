@extends('layouts.tenant')

@section('title', 'Change Password')

@section('content')
    <div class="container px-0" style="max-width: 720px;">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Change Password</h1>
            <p class="text-muted mb-0">
                {{ auth()->user()->must_change_password ? 'Change your initial password to continue.' : 'Update the password you use to sign in.' }}
            </p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                @if ($errors->getBag('updatePassword')->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Password wasn’t changed.</strong> Check the messages below each highlighted field.
                    </div>
                @endif

                @include('profile.partials.update-password-form', [
                    'showPasswordToggle' => true,
                    'passwordRequirements' => \App\Rules\TenantPassword::REQUIREMENTS,
                ])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('#update-password-form .toggle-password').forEach((button) => {
            const input = document.getElementById(button.dataset.target);
            const openEye = button.querySelector('.eye-open');
            const closedEye = button.querySelector('.eye-closed');

            button.addEventListener('click', () => {
                const showPassword = input.type === 'password';

                input.type = showPassword ? 'text' : 'password';
                openEye.classList.toggle('d-none', showPassword);
                closedEye.classList.toggle('d-none', !showPassword);
                button.setAttribute('aria-label', `${showPassword ? 'Hide' : 'Show'} ${button.dataset.fieldLabel}`);
            });
        });
        const newPassword = document.getElementById('update_password_password');
        const requirements = Array.from(
            document.querySelectorAll('#tenant-password-requirements [data-pattern]'),
            (item) => ({
                item,
                pattern: new RegExp(item.dataset.pattern, 'u'),
                icon: item.querySelector('i'),
                status: item.querySelector('[data-requirement-status]'),
            })
        );

        const updateRequirements = () => {
            const value = newPassword.value;
            for (const { item, pattern, icon, status } of requirements) {
                const met = pattern.test(value);
                const missing = value.length > 0 && !met;
                item.classList.toggle('text-success', met);
                item.classList.toggle('text-danger', missing);
                icon.classList.toggle('bi-check-circle-fill', met);
                icon.classList.toggle('bi-x-circle', missing);
                icon.classList.toggle('bi-circle', !met && !missing);
                status.textContent = met ? 'Met. ' : missing ? 'Missing. ' : '';
            }
        };

        newPassword.addEventListener('input', updateRequirements);
        window.addEventListener('pageshow', updateRequirements);
        updateRequirements();
    </script>
@endpush
