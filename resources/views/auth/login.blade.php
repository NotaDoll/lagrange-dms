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

    .lg-login-page {
        min-height: 100vh;
        background-color: #f9f1f3; /* Light pinkish background */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 20px;
        font-family: 'Poppins', sans-serif;
    }

    /* ===== CONTAINER ===== */
    .lg-container {
        width: 100%;
        max-width: 1100px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 40px;
        flex-wrap: wrap; /* Allows stacking on smaller screens */
    }

    /* ===== LEFT BRAND ===== */
    .lg-brand {
        flex: 1;
        min-width: 280px;
    }

    .lg-logo {
        display: flex;
        align-items: center;
        font-family: 'Raleway', sans-serif;
        font-weight: 800;
        font-size: clamp(3rem, 8vw, 4.5rem); /* Responsive Logo size */
        color: #630a4b;
        letter-spacing: -2px;
    }

    .lg-logo .house-icon {
        width: clamp(32px, 5vw, 48px); /* Responsive Icon size */
        height: clamp(32px, 5vw, 48px);
        margin: 0 2px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Fully responsive tagline using clamp */
    .lg-tagline {
        font-family: 'Raleway', sans-serif;
        font-weight: 300;
        font-size: clamp(14px, 2.5vw, 24.64px);
        line-height: 100%;
        letter-spacing: 0;
        color: #630a4b;
        margin-top: 0px;
        margin-bottom: 30px;
        word-break: break-word; /* Prevents text from breaking layout on small phones */
        white-space: normal;
    }

    /* ===== RIGHT WRAPPER ===== */
    .lg-right-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        max-width: 380px;
        width: 100%;
    }

    /* ===== CARD ===== */
    .lg-card {
        background: #fff;
        width: 100%;
        padding: 40px 36px;
        /* Unique rounded corners */
        border-radius: 30px 30px 30px 0;
        box-shadow: 0 8px 25px rgba(99, 10, 75, 0.06);
        margin-bottom: 20px;
    }

    .lg-card .form-label {
        font-weight: 600;
        color: #2b2b2b;
        margin-bottom: 6px;
        font-size: 0.95rem;
    }

    .lg-card .form-control {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.95rem;
        color: #333;
        width: 100%;
    }

    .lg-card .form-control:focus {
        border-color: #630a4b;
        box-shadow: 0 0 0 3px rgba(99, 10, 75, 0.12);
        outline: none;
    }

    /* Green Checkbox */
    .lg-card .form-check-input:checked {
        background-color: #4cd964;
        border-color: #4cd964;
    }
    .lg-card .form-check-input:focus {
        border-color: #4cd964;
        box-shadow: 0 0 0 0.25rem rgba(76, 217, 100, 0.25);
    }
    .lg-card .form-check-label {
        font-size: 0.9rem;
        font-weight: 500;
        color: #333;
        margin-left: 6px;
    }

    /* Login Button */
    .btn-lg-login {
        background-color: #630a4b;
        border: none;
        border-radius: 12px;
        padding: 12px;
        font-weight: 700;
        font-size: 1rem;
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
        margin-top: 18px;
        font-size: 0.85rem;
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
        }

        .lg-right-wrapper {
            align-items: center;
            max-width: 100%;
            padding: 0 15px;
        }

        .lg-card {
            max-width: 400px; /* Prevent card from getting too wide on tablet */
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

        .lg-logo {
            font-size: 2.8rem;
        }
        .lg-logo .house-icon {
            width: 30px;
            height: 30px;
        }

        .lg-tagline {
            margin-bottom: 20px;
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
</style>

<div class="lg-login-page">
    <div class="lg-container">

        <!-- Left Brand Section -->
        <div class="lg-brand">
            <div class="lg-logo">
                l<span class="house-icon">
                    <svg viewBox="0 0 100 100" width="100%" height="100%" fill="#630a4b">
                        <path d="M50 10 L10 40 L10 90 L90 90 L90 40 Z" />
                        <path d="M30 70 Q50 80 70 70" stroke="white" stroke-width="6" stroke-linecap="round" fill="none"/>
                    </svg>
                </span>grange
            </div>
            <div class="lg-tagline">DORMITORY MANAGEMENT SYSTEM</div>
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
                        <input type="checkbox" name="remember" id="remember" class="form-check-input" checked style="margin-top: 0;">
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

