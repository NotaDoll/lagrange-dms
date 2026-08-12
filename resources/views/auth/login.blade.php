@extends('layouts.guest')
@section('title', 'Login')
@section('content')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Raleway:wght@300;700;800&display=swap" rel="stylesheet">

<style>
    /* ===== GLOBAL ===== */
    * {
        box-sizing: border-box;
    }

    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }

    .lg-login-page {
        position: fixed; /* escapes any max-width/centered container from layouts.guest */
        inset: 0;
        min-height: 100vh;
        min-height: 100dvh; /* fixes mobile browser address-bar height jumps */
        width: 100vw;
        background-color: #f9f1f3; /* Light pinkish background */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 20px;
        font-family: 'Poppins', sans-serif;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 0;
    }

    /* ===== CONTAINER ===== */
    .lg-container {
        width: 100%;
        max-width: 1360px; /* tuned so the layout fills a 1440x1024 viewport nicely (1440 - side padding) */
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 60px;
        flex-wrap: wrap; /* Allows stacking on smaller screens */
    }

    /* ===== LEFT BRAND ===== */
    .lg-brand {
        flex: 1;
        min-width: 280px;
    }

    .lg-logo-img {
        display: block;
        width: clamp(220px, 26vw, 380px); /* scales with viewport, same responsive idea as before */
        height: auto;
        margin-bottom: 4px;
    }

    /* ===== RIGHT WRAPPER ===== */
    .lg-right-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        max-width: 548px;
        width: 100%;
    }

    /* ===== CARD ===== */
    .lg-card {
        background: #fff;
        width: 548px;
        height: 609px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 50px 44px;
        /* Unique rounded corners */
        border-radius: 36px 36px 36px 0;
        box-shadow: 0 8px 25px rgba(99, 10, 75, 0.06);
        margin-bottom: 20px;
    }

    .lg-card .form-label {
        font-weight: 600;
        color: #2b2b2b;
        margin-bottom: 8px;
        font-size: 1.05rem;
    }

    .lg-card .form-control {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 13px 16px;
        font-size: 16px; /* keeps iOS Safari from auto-zooming on focus */
        color: #333;
        width: 100%;
        max-width: 100%;
    }

    .lg-card .form-control:focus {
        border-color: #630a4b;
        box-shadow: 0 0 0 3px rgba(99, 10, 75, 0.12);
        outline: none;
    }

    /* Green Checkbox */
    .lg-card .form-check-input {
        width: 19px;
        height: 19px;
        border-radius: 50%;
        accent-color: #4cd964;
    }
    .lg-card .form-check-input:checked {
        background-color: #4cd964;
        border-color: #4cd964;
    }
    .lg-card .form-check-input:focus {
        border-color: #4cd964;
        box-shadow: 0 0 0 0.25rem rgba(76, 217, 100, 0.25);
    }
    .lg-card .form-check-label {
        font-size: 0.95rem;
        font-weight: 500;
        color: #333;
        margin-left: 6px;
    }

    /* Login Button */
    .btn-lg-login {
        background-color: #630a4b;
        border: none;
        border-radius: 14px;
        padding: 14px;
        font-weight: 700;
        font-size: 1.05rem;
        width: 100%;
        color: #fff;
        transition: background-color 0.2s ease;
        margin-top: 8px;
        cursor: pointer;
    }

    .btn-lg-login:hover {
        background-color: #4d083a;
        color: #fff;
    }

    .lg-card .forgot-link {
        display: block;
        text-align: center;
        margin-top: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #b23368;
        text-decoration: none;
    }
    .lg-card .forgot-link:hover {
        text-decoration: underline;
    }

    /* ===== BOTTOM INFO ===== */
    .lg-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 0.75rem;
        color: #e07a8c;
        line-height: 1.5;
        text-align: left;
        max-width: 100%;
    }

    .lg-info .info-icon {
        min-width: 14px;
        height: 14px;
        background-color: #3498db;
        color: #fff;
        font-size: 0.5rem;
        font-weight: 700;
        border-radius: 2px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 3px;
    }

    /* ===== RESPONSIVE BREAKPOINTS ===== */

    /* Tablet and smaller */
    @media (max-width: 900px) {
        .lg-container {
            justify-content: center;
            gap: 30px;
        }

        .lg-brand {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            min-width: 0;
        }

        .lg-right-wrapper {
            align-items: center;
            max-width: 100%;
            padding: 0 15px;
        }

        .lg-card {
            max-width: 548px; /* caps at the same width as desktop, but flexes down on smaller screens */
            width: 100%;
            height: auto;
        }

        .lg-info {
            text-align: center;
            justify-content: center;
            padding: 0 10px;
            max-width: 400px;
        }
    }

    /* Mobile Devices (Phones) */
    @media (max-width: 560px) {
        .lg-login-page {
            padding: 40px 10px;
            align-items: flex-start; /* Aligns to top on small screens so keyboard doesn't cover */
            padding-top: 60px;
        }

        .lg-brand {
            min-width: auto;
            width: 100%;
        }

        .lg-right-wrapper {
            padding: 0;
        }

        .lg-card {
            padding: 30px 20px;
            border-radius: 20px 20px 20px 0;
        }

        .lg-info {
            text-align: left; /* Better readability on mobile */
            justify-content: flex-start;
            padding: 0;
        }
    }

    /* Small / narrow phones (e.g. iPhone SE, older Androids ~360px and under) */
    @media (max-width: 400px) {
        .lg-login-page {
            padding: 30px 8px;
            padding-top: 40px;
        }

        .lg-card {
            padding: 24px 16px;
        }

        .btn-lg-login {
            padding: 11px;
            font-size: 0.95rem;
        }
    }

    /* Short / landscape phone screens, so the card + info don't get cut off */
    @media (max-height: 480px) and (orientation: landscape) {
        .lg-login-page {
            align-items: flex-start;
            padding-top: 24px;
            padding-bottom: 24px;
        }

        .lg-brand {
            margin-bottom: 10px;
        }

        .lg-card {
            padding: 20px 18px;
        }
    }
</style>

<div class="lg-login-page">
    <div class="lg-container">

        <!-- Left Brand Section -->
        <div class="lg-brand">
            <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange - Dormitory Management System" class="lg-logo-img">
        </div>

        <!-- Right Form Section -->
        <div class="lg-right-wrapper">
            <div class="lg-card">

                @if ($errors->any())
                    <div class="alert alert-danger" style="margin-bottom: 15px; font-size: 0.9rem;">
                        <ul class="mb-0" style="padding-left: 20px; margin: 0;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @session('status')
                    <div class="alert alert-success" style="margin-bottom: 15px; font-size: 0.9rem;">{{ $value }}</div>
                @endsession

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3" style="margin-bottom: 15px;">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')
                            <div class="text-danger small mt-1" style="color: #dc3545; font-size: 0.85rem; margin-top: 4px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" style="margin-bottom: 15px;">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="text-danger small mt-1" style="color: #dc3545; font-size: 0.85rem; margin-top: 4px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check" style="display: flex; align-items: center; margin-bottom: 20px;">
                        <input type="checkbox" name="remember" id="remember" class="form-check-input" style="margin-top: 0;">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn-lg-login">Log in</button>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                    @endif
                </form>
            </div>

            <div class="lg-info">
                <span class="info-icon">i</span>
                <span>First-time users: Your login credentials, including your temporary password, were provided by the administrator. You will be required to change your password upon your first login.</span>
            </div>
        </div>

    </div>
</div>

@endsection
