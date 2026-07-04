@extends('layouts.guest')
@section('title', 'Verify Email')
@section('content')
    <p class="text-muted small">Please verify your email address by clicking the link we emailed you.</p>
    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">A new verification link has been sent to your email address.</div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-primary w-100">Resend Verification Email</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary w-100">Log Out</button>
    </form>
@endsection