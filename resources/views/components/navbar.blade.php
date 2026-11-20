@props(['variant' => 'tenant', 'unreadComplaintCount' => 0])

@php
    $user = auth()->user();
    $userInitial = strtoupper(substr($user->name ?? 'U', 0, 1));
    $tenant = $user?->tenant;
    $currentRoom = $tenant?->currentAssignment?->bed?->room?->room_number;
    $roomLabel = $currentRoom ? strtoupper($currentRoom) : 'ROOM N/A';
    $proprietorLinks = [
        ['label' => 'Dashboard', 'route' => 'proprietor.dashboard', 'active' => 'proprietor.dashboard', 'icon' => 'bi-grid-1x2-fill'],
        ['label' => 'Tenant Management', 'route' => 'proprietor.tenants.index', 'active' => 'proprietor.tenants.*', 'icon' => 'bi-people-fill'],
        ['label' => 'Room Management', 'route' => 'proprietor.rooms.index', 'active' => 'proprietor.rooms.*', 'icon' => 'bi-door-closed-fill'],
        ['label' => 'Utility Bills', 'route' => 'proprietor.utility-bills.index', 'active' => 'proprietor.utility-bills.*', 'icon' => 'bi-lightning-charge-fill'],
        ['label' => 'Complaints', 'route' => 'proprietor.complaints.index', 'active' => 'proprietor.complaints.*', 'icon' => 'bi-chat-left-text-fill'],
        [
            'label' => 'Announcements / FAQs',
            'route' => 'proprietor.announcements-faqs.index',
            'active' => ['proprietor.announcements-faqs.index', 'proprietor.announcements.*', 'proprietor.faqs.*'],
            'icon' => 'bi-megaphone-fill',
        ],
    ];

    $reportLinks = [
        ['label' => 'Payment Ledger', 'route' => 'proprietor.payments.index', 'active' => 'proprietor.payments.*', 'icon' => 'bi-credit-card-fill'],
        ['label' => 'Financial Report', 'route' => 'proprietor.reports.index', 'active' => 'proprietor.reports.index', 'icon' => 'bi-bar-chart-fill'],
    ];

    $settingsLinks = [
        ['label' => 'Settings', 'route' => 'proprietor.settings.edit', 'active' => 'proprietor.settings.edit', 'icon' => 'bi-gear-fill'],
    ];
@endphp

@if ($variant === 'tenant')
    <nav class="navbar navbar-expand-lg tenant-navbar d-none d-lg-flex">
        <div class="container-fluid">

            <!-- Brand / Logo Section -->
            <a class="navbar-brand-wrapper" href="{{ route('tenant.dashboard') }}">
                <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS logo" class="navbar-logo" style="height: 42px; width: auto; display: block; ">
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
                    </li>                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tenant.faq.*') ? 'active' : '' }}" href="{{ route('tenant.faq.index') }}">FAQs</a>
                    </li>
                </ul>

                <!-- 3. Empty spacer on the right -->
                <div style="flex: 1;"></div>

                <!-- Right Side Actions (Bell & Avatar Dropdown) -->
                <div class="nav-right-actions">
                    <a href="{{ route('notifications.index') }}" class="nav-notif-wrapper" aria-label="Notifications" onclick="clearNotificationBadge()">
                        <i class="bi bi-bell-fill" aria-hidden="true"></i>
                        <span class="badge notif-badge">0</span>
                    </a><!-- User Avatar Dropdown -->
                    <div class="dropdown">
                        <button class="nav-user-avatar" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $userInitial }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-profile-dropdown">
                            <li class="user-profile-card">
                                <div class="user-profile-summary">
                                    <div class="user-profile-name">{{ $user->name ?? 'User' }}</div>
                                    <span class="user-profile-room">{{ $roomLabel }}</span>
                                </div>
                            </li>
                            <div class="user-profile-divider"></div>
                            <li>
                                <a href="{{ route('tenant.profile.show') }}" class="dropdown-item user-profile-action"
                                    @if (request()->routeIs('tenant.profile.show')) aria-current="page" @endif>
                                    <i class="bi bi-person-vcard" aria-hidden="true"></i> My Information
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('tenant.password.edit') }}" class="dropdown-item user-profile-action">
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

    <nav class="tenant-mobile-topbar d-lg-none" aria-label="Tenant mobile navigation">
        <a href="{{ route('tenant.dashboard') }}" class="tenant-mobile-brand" aria-label="La Grange dashboard">
            <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS logo">
        </a>
        <button class="tenant-mobile-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#tenantSidebar" aria-controls="tenantSidebar" aria-label="Open navigation menu">
            <span class="tenant-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
    </nav>

    <div class="offcanvas offcanvas-start tenant-mobile-sidebar d-lg-none" tabindex="-1" id="tenantSidebar" aria-labelledby="tenantSidebarTitle">
        <div class="offcanvas-header">
            <div class="tenant-sidebar-brand" id="tenantSidebarTitle">
                <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS logo">
                <div class="tenant-sidebar-user">{{ $user->name ?? 'Tenant' }}</div>
                <div class="tenant-sidebar-room">{{ $roomLabel }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div class="tenant-sidebar-section-label">MENU</div>
            <nav class="tenant-sidebar-links" aria-label="Main menu">
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}" href="{{ route('tenant.dashboard') }}"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Dashboard</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.payments.*') ? 'active' : '' }}" href="{{ route('tenant.payments.index') }}"><i class="bi bi-credit-card-fill" aria-hidden="true"></i><span>Payments</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.complaints.*') ? 'active' : '' }}" href="{{ route('tenant.complaints.index') }}"><i class="bi bi-chat-left-text-fill" aria-hidden="true"></i><span>Complaints</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.announcements.*') ? 'active' : '' }}" href="{{ route('tenant.announcements.index') }}"><i class="bi bi-megaphone-fill" aria-hidden="true"></i><span>Announcements</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.faq.*') ? 'active' : '' }}" href="{{ route('tenant.faq.index') }}"><i class="bi bi-question-circle-fill" aria-hidden="true"></i><span>FAQs</span></a>
            </nav>

            <div class="tenant-sidebar-section-label mt-4">ACCOUNT</div>
            <nav class="tenant-sidebar-links" aria-label="Account menu">
                                <a class="tenant-sidebar-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}" onclick="clearNotificationBadge()"><i class="bi bi-bell-fill" aria-hidden="true"></i><span>Notifications</span><span class="badge rounded-pill notif-badge">0</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.profile.show') ? 'active' : '' }}" href="{{ route('tenant.profile.show') }}"
                    @if (request()->routeIs('tenant.profile.show')) aria-current="page" @endif><i class="bi bi-person-vcard" aria-hidden="true"></i><span>My Information</span></a>
                <a class="tenant-sidebar-link {{ request()->routeIs('tenant.password.*') ? 'active' : '' }}" href="{{ route('tenant.password.edit') }}"><i class="bi bi-person-fill" aria-hidden="true"></i><span>Change password</span></a>
            </nav>

            <div class="tenant-sidebar-footer mt-auto">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="tenant-sidebar-logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Logout</button>
                </form>
                <div class="tenant-sidebar-footer-note">La Grange Dormitory</div>
            </div>
        </div>
    </div>
@elseif ($variant === 'proprietor-sidebar')
    <aside class="offcanvas-md offcanvas-start proprietor-sidebar" id="proprietorSidebar" tabindex="-1" aria-label="Proprietor navigation">
        <div class="proprietor-sidebar-header">
            <a href="{{ route('proprietor.dashboard') }}" class="proprietor-logo-link" aria-label="La Grange dashboard">
                <img src="{{ asset('images/lg-logo.png') }}" alt="" class="proprietor-logo">
            </a>
            <button class="btn proprietor-menu-btn" type="button" id="proprietorSidebarToggle"
                aria-label="Collapse sidebar" aria-expanded="true" aria-controls="proprietorSidebar">
                <i class="bi bi-list" id="proprietorSidebarToggleIcon" aria-hidden="true"></i>
            </button>
        </div>

        <nav aria-label="Main menu">
            @foreach ([$proprietorLinks, $reportLinks, $settingsLinks] as $group)
                @if ($loop->index === 1)
                    <div class="proprietor-sidebar-heading">Reports</div>
                @endif
                <ul class="nav flex-column mb-0">
                    @foreach ($group as $link)
                        @php
                            $active = request()->routeIs(...(array) $link['active']);
                            $isComplaints = $link['route'] === 'proprietor.complaints.index';
                            $label = $isComplaints && $unreadComplaintCount > 0
                                ? 'Complaints, ' . $unreadComplaintCount . ' unread'
                                : $link['label'];
                        @endphp
                        <li class="nav-item">
                            <a href="{{ route($link['route']) }}" @class(['nav-link', 'active' => $active])
                                aria-label="{{ $label }}" data-bs-title="{{ $link['label'] }}"
                                @if ($active) aria-current="page" @endif
                                @if ($isComplaints) id="proprietorComplaintsLink" data-unread-count="{{ $unreadComplaintCount }}" @endif>
                                <i class="bi {{ $link['icon'] }}" aria-hidden="true"></i>
                                <span class="proprietor-sidebar-label">{{ $link['label'] }}</span>
                                @if ($isComplaints && $unreadComplaintCount > 0)
                                    <span class="proprietor-unread-badge" aria-hidden="true">{{ $unreadComplaintCount > 99 ? '99+' : $unreadComplaintCount }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="proprietor-sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="proprietor-logout-btn" aria-label="Logout" data-bs-title="Logout">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="proprietor-sidebar-label">Logout</span>
                </button>
            </form>
        </div>
    </aside>
@elseif ($variant === 'proprietor-mobile-topbar')
    <nav class="navbar proprietor-mobile-navbar d-md-none" aria-label="Mobile navigation">
        <div class="container-fluid">
            <button class="btn proprietor-menu-btn" type="button" id="proprietorMobileToggle"
                aria-label="Open navigation" aria-expanded="false" aria-controls="proprietorSidebar">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <a class="navbar-brand mb-0" href="{{ route('proprietor.dashboard') }}">
                <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS logo">
            </a>
        </div>
    </nav>
@endif
