<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — La Grange DMS</title>
    <script>
        try {
            if (localStorage.getItem('proprietor-sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('proprietor-sidebar-collapsed');
            }
        } catch (error) {}
    </script>
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

        .proprietor-sidebar {
            --bs-offcanvas-width: min(300px, 100vw);
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            padding: clamp(.5rem, 1.8vh, 1rem) 1rem;
            background-color: #fff !important;
            border-right: 1px solid #f0f0f0;
            box-shadow: 4px 0 18px rgba(94, 16, 73, 0.05);
        }

        .proprietor-sidebar > * { flex-shrink: 0; }

        .proprietor-sidebar-header {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: clamp(.75rem, 2vh, 1.4rem);
        }

        .proprietor-logo-link {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            min-width: 0;
            min-height: 40px;
        }

        .proprietor-logo {
            max-height: 54px;
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        /* Menu items: roomy, square, full-width highlight bars. */
        .proprietor-sidebar .nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: .85rem;
            white-space: nowrap;
            line-height: 1.35;
            color: #6b7280;
            border-radius: 0;
            font-size: 1rem;
            /* Bleed past the sidebar padding so the highlight spans the full width. */
            margin: 0 -1rem clamp(.2rem, .6vh, .4rem);
            padding: clamp(.45rem, 1.3vh, .75rem) 1.75rem;
            transition: color .2s ease, background-color .2s ease;
        }

        .proprietor-sidebar .nav-link > .bi { flex-shrink: 0; font-size: 1.15rem; }

        .proprietor-sidebar .nav-link:hover {
            color: #1a1a1a;
            background: var(--theme-pink-light);
        }

        .proprietor-sidebar .nav-link.active {
            color: #fffeff;
            font-weight: 600;
            background: var(--theme-plum);
            box-shadow: inset 0 0 0 1px rgba(192, 36, 159, 0.12);
        }

        .proprietor-sidebar-heading {
            color: #6b7280;
            font-size: .75rem;
            font-weight: 600;
            margin: clamp(.6rem, 1.6vh, 1rem) .75rem clamp(.3rem, .8vh, .5rem);
        }

        /* The sidebar's ☰ toggle: a plain icon with a round highlight. */
        #proprietorSidebarToggle {
            width: 40px;
            height: 40px;
            min-height: 40px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            color: var(--theme-plum);
            font-size: 1.35rem;
            background: transparent;
        }
        #proprietorSidebarToggle:hover,
        #proprietorSidebarToggle:focus-visible { background: var(--theme-pink-light); color: var(--theme-plum); }

        .proprietor-unread-badge {
            display: grid;
            place-items: center;
            margin-left: auto;
            min-width: 1.45rem;
            min-height: 1.35rem;
            padding: 0 .24rem;
            border-radius: .35rem;
            background: #eee2f8;
            color: #3b174c;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .proprietor-sidebar-footer {
            margin-top: auto;
            border-top: 1px solid #f0f0f0;
            padding-top: clamp(.35rem, 1vh, .75rem);
        }

        .proprietor-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            width: 100%;
            border: 1px solid var(--theme-plum);
            color: var(--theme-plum);
            border-radius: var(--theme-btn-radius);
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            background: transparent;
            transition: 0.2s ease;
        }

        .proprietor-logout-btn:hover {
            background: var(--theme-pink-light);
            border-color: var(--theme-plum);
            color: var(--theme-plum);
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
            border-radius: var(--theme-btn-radius);
            font-weight: 700;
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
            padding: .3rem .55rem;
        }

        .proprietor-menu-btn:hover {
            background: var(--theme-pink-light);
            color: var(--theme-plum);
        }

        /* Buttons, cards and tables are styled once in partials/theme.blade.php. */

        @media (max-width: 991px) {
            .proprietor-main {
                padding: 24px 18px;
            }
        }
        .dashboard-kpi-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}

.dashboard-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06) !important;
}

.dashboard-kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--theme-pink-light);
    color: var(--theme-plum);
    font-size: 1.2rem;
}

            @media (max-width: 575.98px) {
            .proprietor-main { padding: 18px 14px; }
            .proprietor-mobile-navbar .container-fluid { padding-left: 0; padding-right: 0; }
            .proprietor-mobile-navbar .navbar-brand img { max-width: 150px; height: auto; }
            .card-body { padding: 1rem; }
            .table { min-width: 640px; }
        }
        @media (min-width: 768px) {
            :root { --proprietor-sidebar-width: 300px; }
            :root.proprietor-sidebar-collapsed { --proprietor-sidebar-width: 72px; }

            .proprietor-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 1030;
                width: var(--proprietor-sidebar-width);
                transition: width .2s ease, padding .2s ease;
            }

            .proprietor-content {
                min-width: 0;
                width: calc(100% - var(--proprietor-sidebar-width));
                margin-left: var(--proprietor-sidebar-width);
                transition: width .2s ease, margin-left .2s ease;
            }

            .proprietor-sidebar-collapsed .proprietor-sidebar { padding-inline: .5rem; }
            .proprietor-sidebar-collapsed .proprietor-sidebar-header { justify-content: center; }
            .proprietor-sidebar-collapsed .proprietor-logo-link,
            .proprietor-sidebar-collapsed .proprietor-sidebar-label { display: none; }

            /* Collapsed: each icon sits in a circle, so hover and active highlights are round. */
            .proprietor-sidebar-collapsed .proprietor-sidebar .nav-link {
                justify-content: center;
                width: 44px;
                height: 44px;
                margin: 0 auto clamp(.35rem, 1vh, .6rem);
                padding: 0;
                border-radius: 50%;
            }
            .proprietor-sidebar-collapsed .proprietor-sidebar-header { margin-bottom: clamp(.75rem, 2vh, 1.25rem); }

            .proprietor-sidebar-collapsed .proprietor-sidebar-heading {
                margin: .4rem .25rem;
                border-top: 1px solid #e7dce5;
                font-size: 0;
            }

            .proprietor-sidebar-collapsed .proprietor-logout-btn { width: 44px; height: 44px; padding: 0; margin: 0 auto; border-radius: 50%; }
            .proprietor-sidebar-collapsed .proprietor-unread-badge {
                position: absolute;
                inset: 0 .15rem auto auto;
                min-width: 1rem;
                min-height: 1rem;
                font-size: .59rem;
            }
        }

        @media (max-height: 600px) {
            .proprietor-sidebar .nav-link { font-size: .9rem; padding-block: .22rem; margin-bottom: .1rem; }
            .proprietor-sidebar-header { margin-bottom: .35rem; }
            .proprietor-sidebar-heading { margin-block: .2rem; }
            .proprietor-logout-btn { padding-block: .35rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .proprietor-sidebar, .proprietor-content { transition: none; }
        }

        .proprietor-main { min-width: 0; }
        .table-responsive { -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body>
    <div class="d-flex">
        <x-navbar variant="proprietor-sidebar" :unread-complaint-count="$unreadComplaintCount" />

        <!-- Main content -->
        <div class="flex-grow-1 proprietor-content">
            <x-navbar variant="proprietor-mobile-topbar" />

            <main class="proprietor-main">
                {{-- Pages with their own styled success alert set @section('own_success_alert'). --}}
                @if (session('success') && ! View::hasSection('own_success_alert'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (() => {
            const complaintsLink = document.getElementById('proprietorComplaintsLink');

            document.addEventListener('complaint:read', () => {
                const count = Math.max(0, Number(complaintsLink.dataset.unreadCount) - 1);
                const badge = complaintsLink.querySelector('.proprietor-unread-badge');
                complaintsLink.dataset.unreadCount = count;
                complaintsLink.setAttribute('aria-label', count ? 'Complaints, ' + count + ' unread' : 'Complaints');

                if (count === 0) {
                    badge?.remove();
                } else if (badge) {
                    badge.textContent = count > 99 ? '99+' : count;
                }
            });

            document.addEventListener('DOMContentLoaded', () => {
                const root = document.documentElement;
                const sidebar = document.getElementById('proprietorSidebar');
                const sidebarToggle = document.getElementById('proprietorSidebarToggle');
                const toggleIcon = document.getElementById('proprietorSidebarToggleIcon');
                const mobileToggle = document.getElementById('proprietorMobileToggle');
                const desktop = window.matchMedia('(min-width: 768px)');
                const collapsedClass = 'proprietor-sidebar-collapsed';
                const drawer = window.bootstrap?.Offcanvas;
                const tooltip = window.bootstrap?.Tooltip;
                const tooltipTargets = sidebar.querySelectorAll('[data-bs-title]');

                function syncSidebar() {
                    const collapsed = root.classList.contains(collapsedClass);
                    const expanded = desktop.matches ? !collapsed : sidebar.classList.contains('show');
                    sidebarToggle.setAttribute('aria-expanded', String(expanded));
                    sidebarToggle.setAttribute('aria-label', desktop.matches
                        ? (collapsed ? 'Expand sidebar' : 'Collapse sidebar')
                        : 'Close navigation');
                    // Desktop: the ☰ menu icon both collapses and expands. Phone drawer: ✕ closes it.
                    toggleIcon.className = 'bi ' + (desktop.matches ? 'bi-list' : 'bi-x-lg');

                    tooltipTargets.forEach(target => {
                        if (desktop.matches && collapsed) {
                            tooltip?.getOrCreateInstance(target, { placement: 'right', container: 'body' });
                        } else {
                            tooltip?.getInstance(target)?.dispose();
                        }
                    });
                }

                function toggleSidebar() {
                    if (desktop.matches) {
                        const collapsed = root.classList.toggle(collapsedClass);
                        try { localStorage.setItem(collapsedClass, String(collapsed)); } catch (error) {}
                        syncSidebar();
                    } else {
                        drawer?.getOrCreateInstance(sidebar).toggle(mobileToggle);
                    }
                }

                sidebarToggle.addEventListener('click', toggleSidebar);
                mobileToggle.addEventListener('click', toggleSidebar);
                sidebar.addEventListener('shown.bs.offcanvas', () => {
                    mobileToggle.setAttribute('aria-expanded', 'true');
                    sidebarToggle.setAttribute('aria-expanded', 'true');
                });
                sidebar.addEventListener('hidden.bs.offcanvas', () => {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    syncSidebar();
                    if (!desktop.matches) { mobileToggle.focus(); }
                });
                desktop.addEventListener('change', () => {
                    if (desktop.matches) { drawer?.getInstance(sidebar)?.hide(); }
                    syncSidebar();
                });

                syncSidebar();
            });
        })();
    </script>
    @include('partials.table-pagination')
    @stack('scripts')
</body>
</html>
