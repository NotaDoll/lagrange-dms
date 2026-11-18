@extends(auth()->user()->role === 'proprietor' ? 'layouts.proprietor' : 'layouts.tenant')

@section('title', 'Profile')

@section('content')
    <h1 class="mb-4">Profile</h1>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Profile Information</h5>
                    <p class="text-muted small">Update your account's name and email address.</p>
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Update Password</h5>
                    <p class="text-muted small">Ensure your account is using a long, random password to stay secure.</p>
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- No "Delete Account" here: the proprietor is the only administrator, so deleting this
                 account would lock everyone out of the system. --}}
        </div>
    </div>
@endsection