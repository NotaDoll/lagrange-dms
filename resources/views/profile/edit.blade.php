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

            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">Delete Account</h5>
                    <p class="text-muted small">Once your account is deleted, all of its resources and data will be permanently deleted.</p>
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection