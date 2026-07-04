<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('proprietor.dashboard') }}">La Grange DMS</a>
            <div class="navbar-nav">
                <a class="nav-link" href="{{ route('proprietor.dashboard') }}">Dashboard</a>
                <a class="nav-link" href="{{ route('proprietor.tenants.index') }}">Tenants</a>
                <a class="nav-link" href="{{ route('proprietor.rooms.index') }}">Rooms</a>
                <a class="nav-link" href="{{ route('proprietor.payments.index') }}">Payments</a>
                <a class="nav-link" href="{{ route('proprietor.risk.index') }}">Risk</a>
                <a class="nav-link" href="{{ route('proprietor.complaints.index') }}">Complaints</a>
                <a class="nav-link" href="{{ route('proprietor.announcements.index') }}">Announcements</a>
                <a class="nav-link" href="{{ route('proprietor.faqs.index') }}">FAQs</a>
                <span class="badge bg-danger" id="notif-badge" style="display:none">0</span>
                <form method="POST" action="{{ route('logout') }}" class="d-flex ms-2">
                    @csrf
                    <button class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>
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
    <div class="container mt-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
