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
            margin-right: 8%;
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
            margin-right: 8%;
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
            background-color: #FFE4F9;
            color: #C0249F;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            text-decoration: none;
            border: 2px solid #FF9CE9;
            transition: 0.2s;
            cursor: pointer;
            padding: 0;
            appearance: none;
            -webkit-appearance: none;
        }

        .nav-user-avatar::after {
            display: none !important;
        }

        .nav-user-avatar:hover {
            background-color: var(--c-bg-light);
        }

        .user-profile-dropdown {
            width: 320px;
            border-radius: 18px;
            border: 1px solid rgba(99, 94, 99, 0.2);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.10);
            padding: 14px 16px 10px;
            margin-top: 12px;
            background: #fdfdfd;
        }

        .user-profile-card {
            padding: 4px 4px 10px;
        }

        .user-profile-summary {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .user-profile-name {
            font-size: 20px;
            line-height: 1.05;
            font-weight: 600;
            letter-spacing: -0.05em;
            color: #1f1f1f;
        }

        .user-profile-room-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 1.05rem;
            color: #111827;
            
        }

        .user-profile-room {
            font-weight: 300;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            font-size: 15px;
            color: #1f1f1f;
        }

        .user-profile-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 300;
            color: #2f9e44;
            font-size: 15px;
        }

        .user-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #2f9e44;
            display: inline-block;
        }

        .user-profile-divider {
            height: 1px;
            background: rgba(31, 31, 31, 0.18);
            margin: 10px 0 8px;
        }

        .user-profile-action {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 8px;
            font-size: 1.1rem;
            color: #1f1f1f;
            border-radius: 10px;
            font-weight: 500;
        }

        .user-profile-action svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }

        .user-profile-action:hover {
            background: #f6f6f6;
            color: #111827;
        }

        .user-logout-action {
            color: #b42318;
        }

        .user-logout-action:hover {
            color: #b42318;
            background: #fff3f2;
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
