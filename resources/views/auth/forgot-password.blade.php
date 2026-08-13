@extends('layouts.guest')
@section('title', 'Forgot Password')
@section('content')
    <p class="text-muted small">Enter your email and we'll send you a password reset link.</p>
    @session('status')
        <div class="alert alert-success">{{ $value }}</div>
    @endsession
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus style="border-color: #630a4b; box-shadow: none;">
        </div>
        <button type="submit" class="btn btn-primary w-100" style="background-color: #630a4b; border-color: #630a4b;">Email Password Reset Link</button>
    </form>
@endsection