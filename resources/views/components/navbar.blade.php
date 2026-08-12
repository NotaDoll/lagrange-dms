@props(['variant' => 'tenant'])

@php
    $userInitial = strtoupper(substr(auth()->user()->name ?? 'U', 0, 1));
    $proprietorLinks = [
        ['label' => 'Dashboard', 'route' => 'proprietor.dashboard', 'active' => 'proprietor.dashboard'],
        ['label' => 'Tenants', 'route' => 'proprietor.tenants.index', 'active' => 'proprietor.tenants.*'],
        ['label' => 'Rooms', 'route' => 'proprietor.rooms.index', 'active' => 'proprietor.rooms.*'],
        ['label' => 'Payments', 'route' => 'proprietor.payments.index', 'active' => 'proprietor.payments.*'],
        ['label' => 'Risk', 'route' => 'proprietor.risk.index', 'active' => 'proprietor.risk.*'],
        ['label' => 'Complaints', 'route' => 'proprietor.complaints.index', 'active' => 'proprietor.complaints.*'],
        ['label' => 'Announcements', 'route' => 'proprietor.announcements.index', 'active' => 'proprietor.announcements.*'],
        ['label' => 'FAQs', 'route' => 'proprietor.faqs.index', 'active' => 'proprietor.faqs.*'],
    ];
@endphp

@if ($variant === 'tenant')
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

                <!-- 1. Empty spacer on the left -->
                <div style="flex: 1;"></div>

                <!-- 2. CENTERED NAVIGATION LIST -->
                <!-- Changed `me-auto` to `mx-auto`, and added `d-flex justify-content-center` -->
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 d-flex justify-content-center" style="flex: 2;">
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

                <!-- 3. Empty spacer on the right -->
                <div style="flex: 1;"></div>

                <!-- Right Side Actions (Bell & Avatar Dropdown) -->
                <div class="nav-right-actions">

                    <!-- Bell with Badge (Added onclick and id) -->
                    <a href="{{ route('notifications.index') }}" class="nav-notif-wrapper" id="bellLink" onclick="clearNotificationBadge()">
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
@elseif ($variant === 'proprietor-sidebar')
    <!-- Sidebar: visible on lg+ screens -->
    <nav class="sidebar bg-dark flex-shrink-0 d-none d-lg-flex flex-column p-3">
        <a href="{{ route('proprietor.dashboard') }}" class="d-flex align-items-center mb-3 text-white text-decoration-none fs-5 fw-semibold">
            La Grange DMS
        </a>
        <ul class="nav nav-pills flex-column mb-auto">
            @foreach ($proprietorLinks as $link)
                <li @class(['nav-item' => $loop->first])>
                    <a href="{{ route($link['route']) }}" class="nav-link {{ request()->routeIs($link['active']) ? 'active' : '' }}">{{ $link['label'] }}</a>
                </li>
            @endforeach
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
                @foreach ($proprietorLinks as $link)
                    <li @class(['nav-item' => $loop->first])><a href="{{ route($link['route']) }}" class="nav-link text-white">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
            <hr class="text-white-50">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-light btn-sm w-100">Logout</button>
            </form>
        </div>
    </div>
@elseif ($variant === 'proprietor-mobile-topbar')
    <!-- Top bar: mobile only, just a menu toggle + brand -->
    <nav class="navbar navbar-dark bg-dark d-lg-none">
        <div class="container-fluid">
            <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                &#9776;
            </button>
            <span class="navbar-brand mb-0 h1">La Grange DMS</span>
        </div>
    </nav>
@endif
