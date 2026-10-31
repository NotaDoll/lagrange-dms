@extends('layouts.tenant')

@section('title', 'Change Password')

@section('content')
    <div class="container px-0" style="max-width: 720px;">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Change Password</h1>
            <p class="text-muted mb-0">Update the password you use to sign in.</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
@endsection
