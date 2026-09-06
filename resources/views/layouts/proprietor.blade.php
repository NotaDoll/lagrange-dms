<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        :root {
            --c-primary: #C0249F;
            --c-bg-light: #FF9CE9;
            --c-bg-white: #FFE4F9;
        }

        body {
            background: #fff;
            color: var(--theme-text-dark);
        }

        .sidebar {
            width: 270px;
            min-height: 100vh;
            background: #ffffff;
            border-right: 1px solid #f0f0f0;
            box-shadow: 4px 0 18px rgba(94, 16, 73, 0.05);
            padding: 22px 18px;
        }

        .proprietor-logo-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 0 24px;
            margin-bottom: 10px;
            text-decoration: none;
        }

        .proprietor-logo {
            height: 54px;
            width: auto;
            display: block;
        }

        .sidebar .nav-link {
            color: #9ca3af;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 500;
            padding: 11px 18px;
            margin-bottom: 6px;
            transition: color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            color: #1a1a1a;
            background-color: var(--theme-pink-light);
        }

        .sidebar .nav-link.active {
            color: var(--theme-plum);
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(192, 36, 159, 0.12);
        }

        .proprietor-sidebar-footer {
            border-top: 1px solid #f0f0f0;
            padding-top: 16px;
            margin-top: 18px;
        }

        .proprietor-notification-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            color: #6b7280;
            border-radius: 50%;
            text-decoration: none;
        }

        .proprietor-notification-link:hover {
            color: var(--theme-plum);
            background: var(--theme-pink-light);
        }

        .proprietor-notification-link svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }

        #notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background-color: #dc2626;
            color: #fff;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            border: 2px solid #fff;
            line-height: 1;
            min-width: 16px;
            text-align: center;
        }

        .proprietor-logout-btn {
            border: 1px solid var(--theme-plum);
            color: var(--theme-plum);
            border-radius: 999px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            background: transparent;
            transition: 0.2s ease;
        }

        .proprietor-logout-btn:hover {
            background: var(--theme-plum);
            border-color: var(--theme-plum);
            color: #fff;
        }

        .proprietor-content {
            min-height: 100vh;
            background: #fff;
        }

        .proprietor-main {
            padding: 34px 40px;
        }

        .proprietor-mobile-navbar {
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 16px;
        }

        .proprietor-mobile-navbar .navbar-brand img {
            height: 42px;
            width: auto;
        }

        .proprietor-menu-btn {
            border: 1px solid var(--theme-plum);
            color: var(--theme-plum);
            border-radius: 999px;
            font-weight: 700;
            padding: 6px 14px;
        }

        .proprietor-menu-btn:hover {
            background: var(--theme-pink-light);
            color: var(--theme-plum);
        }

        .proprietor-offcanvas {
            background: #ffffff;
            color: var(--theme-text-dark);
        }

        .proprietor-offcanvas .offcanvas-header {
            border-bottom: 1px solid #f0f0f0;
        }

        .proprietor-offcanvas .offcanvas-title img {
            height: 42px;
            width: auto;
        }

        .proprietor-offcanvas .nav-link {
            color: #9ca3af;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 500;
            padding: 11px 18px;
            margin-bottom: 6px;
        }

        .proprietor-offcanvas .nav-link.active,
        .proprietor-offcanvas .nav-link:hover {
            color: var(--theme-plum);
            background: var(--theme-pink-light);
            font-weight: 700;
        }

        .btn-primary {
            background: var(--theme-plum);
            border-color: var(--theme-plum);
            border-radius: 999px;
            font-weight: 600;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--theme-plum-hover);
            border-color: var(--theme-plum-hover);
        }

        .btn-outline-primary {
            color: var(--theme-plum);
            border-color: var(--theme-plum);
            border-radius: 999px;
            font-weight: 600;
        }

        .btn-outline-primary:hover,
        .btn-outline-primary:focus {
            color: #fff;
            background: var(--theme-plum);
            border-color: var(--theme-plum);
        }

        .card {
            border-color: var(--theme-border);
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02) !important;
        }

        .table thead th {
            background-color: var(--theme-pink-light);
            color: var(--theme-plum);
            border: none;
        }

        @media (max-width: 991px) {
            .proprietor-main {
                padding: 24px 18px;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <x-navbar variant="proprietor-sidebar" />

        <!-- Main content -->
        <div class="flex-grow-1 proprietor-content">
            <x-navbar variant="proprietor-mobile-topbar" />

            <main class="proprietor-main">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
    function clearNotificationBadge() {
        const badge = document.getElementById('notif-badge');
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }

    async function pollNotifications() {
        try {
            const res = await fetch("{{ route('notifications.unread-count') }}");
            const data = await res.json();
            const badge = document.getElementById('notif-badge');
            if (data.count > 0 && !window.location.pathname.includes('/notifications')) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.pathname.includes('/notifications')) {
            clearNotificationBadge();
        }
    });

    pollNotifications();
    setInterval(pollNotifications, 15000);
    </script>
    @stack('scripts')
</body>
</html>
