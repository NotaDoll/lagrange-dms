<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Loading BOTH Raleway and Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Raleway:wght@300;700;800&display=swap" rel="stylesheet">

    <style>
        /* ===== NAVBAR CUSTOM STYLES ===== */
        :root {
            /* New color palette based on your screenshot */
            --c-primary: #C0249F;
            --c-bg-light: #FF9CE9;
            --c-bg-white: #FFE4F9;
        }

        .tenant-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
            font-family: 'Poppins', sans-serif;
        }

        .tenant-navbar .container-fluid {
            padding: 0 40px;
        }

        /* Brand / Logo (Uses Raleway) */
        .navbar-brand-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-decoration: none;
            margin-right: 40px;
        }

        .navbar-logo {
            display: flex;
            align-items: center;
            font-family: 'Raleway', sans-serif;
            font-weight: 800;
            font-size: 2.2rem;
            color: #5E1049; /* Deep plum color */
            letter-spacing: -1.5px;
            line-height: 1;
        }

        .navbar-logo .house-icon {
            width: 32px;
            height: 32px;
            margin: 0 2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-tagline {
            font-family: 'Raleway', sans-serif;
            font-weight: 300;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: #5E1049;
            margin-top: 2px;
            margin-left: 2px;
            line-height: 1;
        }

        /* Nav Links (Uses Poppins) */
        .tenant-navbar .nav-link {
            color: #9ca3af; /* Muted gray */
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            padding: 8px 16px !important;
            transition: color 0.2s;
            align-items: center;
        }

        .tenant-navbar .nav-link:hover {
            color: #5E1049;
        }

        .tenant-navbar .nav-link.active {
            color: #1a1a1a; /* Active black */
            font-weight: 600;
        }

        /* Right Side Icons */
        .nav-right-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto; /* Pushes to the far right */
        }

        .nav-notif-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            color: #6b7280;
            text-decoration: none;
        }
        .nav-notif-wrapper:hover { color: #5E1049; }

        .nav-notif-icon {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        /* Notification Pill */
        #notif-badge {
            position: absolute;
            top: -6px;
            right: -8px;
            background-color: #dc2626;
            color: white;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            display: none;
            line-height: 1;
            min-width: 16px;
            text-align: center;
        }

        /* User Avatar with new color palette */
        .nav-user-avatar {
            width: 36px;
            height: 36px;
            background-color: var(--c-bg-white); /* #FFE4F9 */
            color: var(--c-primary);             /* #C0249F - Applied here! */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;       /* Extra Bold */
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            text-decoration: none;
            border: 2px solid #fff;
            transition: 0.2s;
            cursor: pointer;
        }
        .nav-user-avatar:hover {
            background-color: var(--c-bg-light); /* #FF9CE9 on hover */
        }

        /* Responsive adjustments */
        @media (max-width: 991px) {
            .tenant-navbar .container-fluid { padding: 0 20px; }
            .navbar-brand-wrapper { margin-right: 0; width: 100%; align-items: center; margin-bottom: 10px; }
            .nav-right-actions { margin-left: 0; width: 100%; justify-content: center; padding-top: 10px; border-top: 1px solid #f0f0f0; }
            .nav-notif-icon { width: 20px; height: 20px; }
            #notif-badge { top: -4px; right: -6px; }
        }
    </style>
</head>
<body>

    @php
        // Get the first letter of the user's first name for the avatar.
        $userInitial = strtoupper(substr(auth()->user()->name ?? 'U', 0, 1));
    @endphp

    <nav class="navbar navbar-expand-lg tenant-navbar">
        <div class="container-fluid">

            <!-- Brand / Logo Section -->
            <a class="navbar-brand-wrapper" href="{{ route('tenant.dashboard') }}">
                <div class="navbar-logo">
                    l<span class="house-icon">
                        <!-- Using your exact house icon SVG -->
                        <svg viewBox="0 0 100 100" width="100%" height="100%" fill="#5E1049">
                            <path d="M50 10 L10 40 L10 90 L90 90 L90 40 Z" />
                            <path d="M30 70 Q50 80 70 70" stroke="white" stroke-width="6" stroke-linecap="round" fill="none"/>
                        </svg>
                    </span>grange
                </div>
                <div class="navbar-tagline">DORMITORY MANAGEMENT SYSTEM</div>
            </a>

            <!-- Hamburger Toggle for Mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#tenantNavbar" aria-controls="tenantNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Links & Right Side Actions -->
            <div class="collapse navbar-collapse" id="tenantNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}" href="{{ route('tenant.dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tenant.payments.*') ? 'active' : '' }}" href="{{ route('tenant.payments.index') }}">My Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tenant.complaints.*') ? 'active' : '' }}" href="{{ route('tenant.complaints.index') }}">Complaints</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tenant.announcements.*') ? 'active' : '' }}" href="{{ route('tenant.announcements.index') }}">Announcements</a>
                    </li>
                </ul>

                <!-- Right Side Actions (Bell & Avatar Dropdown) -->
                <div class="nav-right-actions">

                    <!-- Bell with Badge -->
                    <a href="{{ route('notifications.index') }}" class="nav-notif-wrapper">
                        <!-- Bell Icon SVG -->
                        <svg class="nav-notif-icon" viewBox="0 0 24 24">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="badge" id="notif-badge">0</span>
                    </a>

                    <!-- User Avatar Dropdown -->
                    <div class="dropdown">
                        <button class="nav-user-avatar dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $userInitial }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    async function pollNotifications() {
        const res = await fetch("{{ route('notifications.unread-count') }}");
        const data = await res.json();
        const badge = document.getElementById('notif-badge');
        if (data.count > 0) {
            badge.textContent = data.count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
    pollNotifications();
    setInterval(pollNotifications, 15000);
    </script>

    @stack('scripts')
</body>
</html>
