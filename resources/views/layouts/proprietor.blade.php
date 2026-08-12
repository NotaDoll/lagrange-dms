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
        <!-- Sidebar: visible on lg+ screens -->
        <nav class="sidebar bg-dark flex-shrink-0 d-none d-lg-flex flex-column p-3">
            <a href="{{ route('proprietor.dashboard') }}" class="d-flex align-items-center mb-3 text-white text-decoration-none fs-5 fw-semibold">
                La Grange DMS
            </a>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="{{ route('proprietor.dashboard') }}" class="nav-link {{ request()->routeIs('proprietor.dashboard') ? 'active' : '' }}">Dashboard</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.tenants.index') }}" class="nav-link {{ request()->routeIs('proprietor.tenants.*') ? 'active' : '' }}">Tenants</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.rooms.index') }}" class="nav-link {{ request()->routeIs('proprietor.rooms.*') ? 'active' : '' }}">Rooms</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.payments.index') }}" class="nav-link {{ request()->routeIs('proprietor.payments.*') ? 'active' : '' }}">Payments</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.risk.index') }}" class="nav-link {{ request()->routeIs('proprietor.risk.*') ? 'active' : '' }}">Risk</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.complaints.index') }}" class="nav-link {{ request()->routeIs('proprietor.complaints.*') ? 'active' : '' }}">Complaints</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.announcements.index') }}" class="nav-link {{ request()->routeIs('proprietor.announcements.*') ? 'active' : '' }}">Announcements</a>
                </li>
                <li>
                    <a href="{{ route('proprietor.faqs.index') }}" class="nav-link {{ request()->routeIs('proprietor.faqs.*') ? 'active' : '' }}">FAQs</a>
                </li>
            </ul>
            <hr class="text-white-50">
            <div class="d-flex align-items-center justify-content-between">
                <span class="badge bg-danger" id="notif-badge" style="display:none">0</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </nav>

        <!-- Offcanvas sidebar: mobile only -->
        <div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="sidebarOffcanvas">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">La Grange DMS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item"><a href="{{ route('proprietor.dashboard') }}" class="nav-link text-white">Dashboard</a></li>
                    <li><a href="{{ route('proprietor.tenants.index') }}" class="nav-link text-white">Tenants</a></li>
                    <li><a href="{{ route('proprietor.rooms.index') }}" class="nav-link text-white">Rooms</a></li>
                    <li><a href="{{ route('proprietor.payments.index') }}" class="nav-link text-white">Payments</a></li>
                    <li><a href="{{ route('proprietor.risk.index') }}" class="nav-link text-white">Risk</a></li>
                    <li><a href="{{ route('proprietor.complaints.index') }}" class="nav-link text-white">Complaints</a></li>
                    <li><a href="{{ route('proprietor.announcements.index') }}" class="nav-link text-white">Announcements</a></li>
                    <li><a href="{{ route('proprietor.faqs.index') }}" class="nav-link text-white">FAQs</a></li>
                </ul>
                <hr class="text-white-50">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-light btn-sm w-100">Logout</button>
                </form>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex-grow-1">
            <!-- Top bar: mobile only, just a menu toggle + brand -->
            <nav class="navbar navbar-dark bg-dark d-lg-none">
                <div class="container-fluid">
                    <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                        &#9776;
                    </button>
                    <span class="navbar-brand mb-0 h1">La Grange DMS</span>
                </div>
            </nav>

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