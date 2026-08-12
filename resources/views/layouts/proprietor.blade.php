<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .sidebar {
            width: 240px;
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, .75);
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <x-navbar variant="proprietor-sidebar" />

        <!-- Main content -->
        <div class="flex-grow-1">
            <x-navbar variant="proprietor-mobile-topbar" />

            <main class="p-4">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

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
