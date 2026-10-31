<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

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
            padding: 12px 12px;
        }

        .tenant-navbar .container-fluid {
            padding: 10px 50px;
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
            font-size: 1.1rem;
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
        #notif-badge, .tenant-navbar .notif-badge {
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
            #notif-badge, .tenant-navbar .notif-badge { top: -4px; right: -6px; }

            /* On mobile, reset the nav list margin so it stacks neatly below the centered logo */
            .navbar-nav { margin: 0 auto !important; text-align: center; }
        }
            .tenant-navbar .container-fluid { min-width: 0; }
        .tenant-navbar .navbar-collapse { min-width: 0; }
        .tenant-navbar .navbar-nav { min-width: 0; }
        @media (max-width: 991.98px) {
            .tenant-navbar .container-fluid { padding: 0 16px; }
            .navbar-brand-wrapper { width: auto; align-items: flex-start; margin: 0; }
            .navbar-logo { max-width: 180px; height: auto !important; }
            .tenant-navbar .navbar-collapse { padding: 12px 0 4px; }
            .tenant-navbar .navbar-nav { align-items: stretch; text-align: left; }
            .tenant-navbar .nav-link { font-size: 1rem; padding: 10px 12px !important; }
            .nav-right-actions { width: auto; justify-content: flex-start; padding-top: 12px; margin: 8px 0 0; border-top: 1px solid #f0f0f0; }
            .user-profile-dropdown { width: min(320px, calc(100vw - 32px)); }
        }
        @media (max-width: 575.98px) {
            .container.mt-4 { margin-top: 1rem !important; }
            .tenant-navbar { padding: 8px 0; }
            .tenant-navbar .container-fluid { padding: 0 12px; }
        }
            /* Phone and tablet layout */
        .tenant-navbar .container-fluid {
            max-width: 1440px;
            margin-inline: auto;
            padding-inline: clamp(12px, 4vw, 50px);
        }
        .tenant-navbar .navbar-collapse,
        .tenant-navbar .navbar-nav,
        .tenant-navbar .container-fluid { min-width: 0; }
        .tenant-navbar .navbar-brand-wrapper { margin-right: 1rem; }
        .tenant-navbar .nav-link { white-space: normal; }
        .tenant-navbar .navbar-toggler { flex-shrink: 0; }
        .tenant-navbar .navbar-toggler:focus { box-shadow: 0 0 0 .2rem rgba(94, 16, 73, .2); }
        .tenant-navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%235e1049' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        .container.mt-4 { width: 100%; max-width: 1320px; }
        .table-responsive { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-responsive > .cp-table,
        .table-responsive > .theme-table { min-width: 640px; }
        .cp-create-wrapper { width: 100%; }
        .cp-create-card { min-width: 0; }
        .modal-dialog { width: auto; max-width: calc(100vw - 1.5rem); margin-inline: auto; }
        .modal-content { overflow-wrap: anywhere; }

        @media (max-width: 991.98px) {
            .tenant-navbar .container-fluid { padding: 0 16px; flex-wrap: wrap; }
            .tenant-navbar .navbar-brand-wrapper { width: auto; margin: 0; align-items: flex-start; }
            .tenant-navbar .navbar-logo { width: auto; max-width: min(180px, 55vw); height: 42px !important; }
            .tenant-navbar .navbar-toggler { margin-left: auto; }
            .tenant-navbar .navbar-collapse { flex-basis: 100%; width: 100%; padding: 8px 0 4px; }
            #tenantNavbar > div[style] { display: none !important; }
            .tenant-navbar .navbar-nav { width: 100%; align-items: stretch; text-align: left; margin: 0 !important; }
            .tenant-navbar .nav-link { padding: 10px 12px !important; font-size: 1rem; }
            .nav-right-actions { width: 100%; justify-content: flex-start; gap: 18px; padding: 12px 8px 4px; margin: 8px 0 0; border-top: 1px solid #f0f0f0; }
            .user-profile-dropdown { width: min(320px, calc(100vw - 32px)); }
            .container.mt-4 { padding-inline: 16px; }
        }
        @media (max-width: 575.98px) {
            .tenant-navbar { padding: 8px 0; }
            .tenant-navbar .container-fluid { padding-inline: 12px; }
            .container.mt-4 { margin-top: 1rem !important; padding-inline: 12px; }
            .db-wrapper { margin-top: 0; }
            .db-info-banner { margin-top: 16px; margin-bottom: 20px; padding: 12px; gap: 10px; align-items: flex-start; }
            .db-header h1 { margin-bottom: 16px; }
            .db-cards-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; margin-bottom: 20px; }
            .db-card { padding: 18px 16px; }
            .db-trans-header { padding: 14px 16px; gap: 10px; flex-wrap: wrap; }
            .db-emergency-banner { padding: 16px; }
            .cp-create-wrapper { margin: 16px auto; }
            .cp-create-card { padding: 24px 16px; border-radius: 16px; }
            .cp-create-card h2 { font-size: 1.25rem; margin-bottom: 20px; }
            .cp-action-row { flex-direction: column-reverse; gap: 10px; margin-top: 22px; }
            .cp-action-row > * { width: 100%; text-align: center; justify-content: center; }
            .cp-header { align-items: stretch; gap: 12px; margin-bottom: 16px; }
            .cp-header h1 { font-size: 1.45rem; }
            .cp-submit-btn { width: 100%; }
            .cp-filter-bar { gap: 12px; }
            .cp-filter-btn { align-self: stretch; justify-content: center; min-height: 44px; }
            .cp-filter-popup { width: calc(100vw - 24px); max-width: 420px; right: 0; }
            .cp-card { margin-bottom: 20px; }
            .modal-dialog { max-width: calc(100vw - 1rem); margin: .5rem auto; }
            .ann-modal .modal-content { padding: 22px 18px; }
            .cp-modal .modal-content { padding: 20px 14px; }
        }
            .tenant-mobile-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 64px;
            padding: 10px 16px;
            background: #fff;
            border-bottom: 1px solid #f0e8ee;
            box-shadow: 0 2px 10px rgba(94, 16, 73, .04);
        }
        .tenant-mobile-brand { display: inline-flex; align-items: center; min-width: 0; }
        .tenant-mobile-brand img { display: block; width: auto; height: 40px; max-width: min(190px, 62vw); object-fit: contain; }
        .tenant-mobile-menu-btn {
            display: inline-flex;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            align-items: center;
            justify-content: center;
            border: 1px solid #ead7e5;
            border-radius: 12px;
            background: #fff;
            color: var(--c-primary);
        }
        .tenant-mobile-menu-btn:focus-visible { outline: 3px solid rgba(192, 36, 159, .25); outline-offset: 2px; }
        .tenant-menu-icon { display: flex; width: 19px; flex-direction: column; gap: 4px; }
        .tenant-menu-icon span { display: block; height: 2px; width: 100%; border-radius: 2px; background: currentColor; }
        .tenant-mobile-sidebar { --bs-offcanvas-width: min(86vw, 340px); border: 0; background: #fff; color: #1f1f1f; }
        .tenant-mobile-sidebar .offcanvas-header { display: flex; align-items: flex-start; padding: 22px 20px 18px; border-bottom: 1px solid #f0e8ee; }
        .tenant-sidebar-brand img { display: block; width: auto; height: 38px; max-width: 190px; object-fit: contain; margin-bottom: 16px; }
        .tenant-sidebar-user { font-size: 1rem; font-weight: 700; color: #271522; }
        .tenant-sidebar-room { margin-top: 3px; color: #7b6b78; font-size: .82rem; }
        .tenant-mobile-sidebar .offcanvas-body { padding: 18px 16px 16px; }
        .tenant-sidebar-section-label { margin: 0 10px 8px; color: #988b95; font-size: .68rem; font-weight: 700; letter-spacing: .12em; }
        .tenant-sidebar-links { display: grid; gap: 5px; }
        .tenant-sidebar-link { display: flex; min-height: 48px; align-items: center; gap: 13px; padding: 10px 12px; border-radius: 12px; color: #5e5360; font-size: .95rem; font-weight: 500; text-decoration: none; }
        .tenant-sidebar-link i { width: 20px; color: #8a6b82; font-size: 1.05rem; text-align: center; }
        .tenant-sidebar-link.active { background: #fdf2f8; color: #5e1049; font-weight: 700; }
        .tenant-sidebar-link.active i { color: #5e1049; }
        .tenant-mobile-sidebar .notif-badge { display: none; }
        .tenant-sidebar-link .notif-badge { margin-left: auto; background: #dc2626; color: #fff; }
        .tenant-sidebar-link:not(.active):hover { background: #faf7f9; color: #5e1049; }
        .tenant-sidebar-footer { padding-top: 16px; border-top: 1px solid #f0e8ee; }
        .tenant-sidebar-logout { display: flex; width: 100%; min-height: 48px; align-items: center; gap: 13px; padding: 10px 12px; border: 0; border-radius: 12px; background: transparent; color: #b42318; font-size: .95rem; font-weight: 600; text-align: left; }
        .tenant-sidebar-logout:hover { background: #fff3f2; }
        .tenant-sidebar-footer-note { margin: 14px 12px 0; color: #a79aa3; font-size: .75rem; }
        .tenant-navbar .notif-badge { display: none; }

        @media (max-width: 575.98px) {
            .tenant-mobile-topbar { min-height: 60px; padding: 8px 12px; }
            .tenant-mobile-brand img { height: 36px; }
            .tenant-mobile-sidebar .offcanvas-header { padding: 18px 16px 16px; }
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
        function clearNotificationBadge() {
            document.querySelectorAll('.tenant-navbar .notif-badge, .tenant-mobile-sidebar .notif-badge').forEach((badge) => {
                badge.style.display = 'none';
                badge.textContent = '0';
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.pathname.includes('/notifications')) {
                clearNotificationBadge();
            }
        });

        async function pollNotifications() {
            try {
                const response = await fetch("{{ route('notifications.unread-count') }}");
                const data = await response.json();
                const shouldShow = data.count > 0 && !window.location.pathname.includes('/notifications');

                document.querySelectorAll('.tenant-navbar .notif-badge, .tenant-mobile-sidebar .notif-badge').forEach((badge) => {
                    badge.textContent = data.count;
                    badge.style.display = shouldShow ? 'inline-block' : 'none';
                });
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








