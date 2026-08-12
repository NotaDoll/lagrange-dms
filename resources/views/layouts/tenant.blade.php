<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --c-primary: #C0249F;
            --c-bg-light: #FF9CE9;
            --c-bg-white: #FFE4F9;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        .tenant-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }

        .tenant-navbar .container-fluid {
            padding: 0 40px;
        }

        .navbar-brand-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-decoration: none;
            margin-right: 0;
        }

        .navbar-logo {
            display: flex;
            align-items: center;
            font-weight: 800;
            font-size: 2.2rem;
            color: #5E1049;
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
            font-weight: 300;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: #5E1049;
            margin-top: 2px;
            margin-left: 2px;
            line-height: 1;
        }

        .tenant-navbar .nav-link {
            color: #9ca3af;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 8px 16px !important;
            transition: color 0.2s;
            align-items: center;
        }

        .tenant-navbar .nav-link:hover {
            color: #5E1049;
        }

        .tenant-navbar .nav-link.active {
            color: #1a1a1a;
            font-weight: 600;
        }

        /* Right Side Icons */
        .nav-right-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: 0;
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

        .nav-user-avatar {
            width: 36px;
            height: 36px;
            background-color: var(--c-bg-white);
            color: var(--c-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            text-decoration: none;
            border: 2px solid #fff;
            transition: 0.2s;
            cursor: pointer;
        }
        .nav-user-avatar:hover {
            background-color: var(--c-bg-light);
        }

        /* Responsive adjustments */
        @media (max-width: 991px) {
            .tenant-navbar .container-fluid { padding: 0 20px; }
            .navbar-brand-wrapper { margin-right: 0; width: 100%; align-items: center; margin-bottom: 10px; }
            .nav-right-actions { margin-left: 0; width: 100%; justify-content: center; padding-top: 10px; border-top: 1px solid #f0f0f0; }
            .nav-notif-icon { width: 20px; height: 20px; }
            #notif-badge { top: -4px; right: -6px; }

            /* On mobile, reset the nav list margin so it stacks neatly below the centered logo */
            .navbar-nav { margin: 0 auto !important; text-align: center; }
        }
    </style>

    @include('partials.theme')
</head>
<body>

    <x-navbar variant="tenant" />

    <!-- Main Content -->
    <div class="container mt-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // 1. Function to clear the badge immediately when clicked
        function clearNotificationBadge() {
            const badge = document.getElementById('notif-badge');
            if (badge) {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
        }

        // 2. Automatically hide badge if the user is currently viewing the notifications page
        document.addEventListener('DOMContentLoaded', function() {
            const currentUrl = window.location.pathname;
            // Adjust this string to match your actual notifications route
            const notificationRoute = '/notifications';

            if (currentUrl.includes(notificationRoute)) {
                clearNotificationBadge();
            }
        });

        // 3. Background polling logic
        async function pollNotifications() {
            try {
                const res = await fetch("{{ route('notifications.unread-count') }}");
                const data = await res.json();
                const badge = document.getElementById('notif-badge');

                // Only show the badge if the user is NOT currently on the notifications page AND has unread items
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

        pollNotifications();
        setInterval(pollNotifications, 15000);
    </script>

    @stack('scripts')
</body>
</html>
