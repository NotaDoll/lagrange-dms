@props(['variant' => 'tenant'])

@php
    $user = auth()->user();
    $userInitial = strtoupper(substr($user->name ?? 'U', 0, 1));
    $tenant = $user?->tenant;
    $currentRoom = $tenant?->currentAssignment?->bed?->room?->room_number;
    $roomLabel = $currentRoom ? strtoupper($currentRoom) : 'ROOM N/A';
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
                <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS logo" class="navbar-logo" style="height: 42px; width: auto; display: block; margin-left: 60%;">
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
                        <a class="nav-link {{ request()->routeIs('tenant.payments.*') ? 'active' : '' }}" href="{{ route('tenant.payments.index') }}">Payments</a>
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
                       <svg width="24" height="24" viewBox="0 0 0.72 0.72" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.444 0.595a0.023 0.023 0 0 1 0.022 0.034 0.093 0.093 0 0 1 -0.025 0.029c-0.011 0.008 -0.023 0.014 -0.036 0.019s-0.027 0.006 -0.041 0.006 -0.028 -0.002 -0.041 -0.006 -0.025 -0.01 -0.036 -0.019a0.093 0.093 0 0 1 -0.025 -0.029 0.023 0.023 0 0 1 0.022 -0.034c0.006 0.001 0.051 0.005 0.08 0.005s0.075 -0.004 0.08 -0.005M0.256 0.063a0.259 0.259 0 0 1 0.216 -0.003l0.006 0.003c0.072 0.032 0.118 0.102 0.118 0.178v0.038c0 0.03 0.007 0.061 0.02 0.088l0.008 0.017c0.037 0.076 -0.011 0.164 -0.097 0.18l-0.005 0.001a0.915 0.915 0 0 1 -0.32 0c-0.087 -0.015 -0.132 -0.108 -0.091 -0.182l0.007 -0.012A0.198 0.198 0 0 0 0.146 0.272V0.234C0.146 0.161 0.188 0.095 0.256 0.063" fill-rule="evenodd" clip-rule="evenodd" fill="#000"/></svg>
                        <span class="badge" id="notif-badge">0</span>
                    </a>

                    <!-- User Avatar Dropdown -->
                    <div class="dropdown">
                        <button class="nav-user-avatar" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $userInitial }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-profile-dropdown">
                            <li class="user-profile-card">
                                <div class="user-profile-summary">
                                    <div class="user-profile-name">{{ $user->name ?? 'User' }}</div>
                                    <div class="user-profile-room-row">
                                        <span class="user-profile-room">{{ $roomLabel }}</span>
                                        <span class="user-profile-status"><span class="user-status-dot"></span>Active</span>
                                    </div>
                                </div>
                            </li>
                            <div class="user-profile-divider"></div>
                            <li>
                                <a href="{{ route('profile.edit') }}" class="dropdown-item user-profile-action">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M7 10V8a5 5 0 0 1 10 0v2M6 10h12a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Change Password
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item user-profile-action user-logout-action">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Logout
                                    </button>
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
