<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Nagarjuna KOT - Restaurant Management</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Chart.js for data visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --theme-primary: #10b981;
            --theme-primary-hover: #059669;
            --theme-primary-dark: #047857;
            --theme-primary-light: #ecfdf5;
            --theme-sidebar-bg: #0c1e17;
            --theme-sidebar-hover: #132c22;
            --theme-sidebar-text: #9ca3af;
            --theme-sidebar-muted: #6b7280;
            --theme-page-bg: #f8fafc;
            --theme-card-bg: #ffffff;
            --theme-card-border: #eef2f6;
            --theme-text-main: #0f172a;
            --theme-text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--theme-page-bg);
            color: var(--theme-text-main);
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* App Flex Layout (Matching Reference Widths & Proportions) */
        .app-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Sidebar: Fixed 250px compact width matching Dashcart reference */
        .app-sidebar {
            width: 250px;
            min-width: 250px;
            max-width: 250px;
            flex-shrink: 0;
            background-color: var(--theme-sidebar-bg);
            height: 100vh;
            max-height: 100vh;
            position: sticky;
            top: 0;
            padding: 1.25rem 1rem 1rem;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            z-index: 100;
            overflow: hidden;
        }

        /* Independent Sidebar Nav Scrolling */
        .sidebar-nav-scroll {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 2px;
            margin-right: -2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.16) transparent;
        }

        .sidebar-nav-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-nav-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-nav-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.16);
            border-radius: 4px;
        }
        .sidebar-nav-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.32);
        }

        /* Main Content: Takes 100% of remaining width with generous breathing room */
        .app-main {
            flex: 1 1 auto;
            min-width: 0;
            background-color: var(--theme-page-bg);
            padding: 1.25rem 2.25rem 2.5rem;
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                display: none !important;
            }
            .app-main {
                padding: 1rem 1rem 2rem;
            }
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0.5rem 1.25rem 0.5rem;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 1.25rem;
        }

        .sidebar-brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }

        .sidebar-brand-text h1 {
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .sidebar-brand-text span {
            color: var(--theme-sidebar-muted);
            font-size: 0.72rem;
            font-weight: 500;
            display: block;
        }

        .sidebar-heading {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--theme-sidebar-muted);
            font-weight: 700;
            padding: 0.85rem 0.75rem 0.35rem;
        }

        .app-sidebar .nav-link,
        .offcanvas-body .nav-link {
            color: var(--theme-sidebar-text);
            font-size: 0.88rem;
            font-weight: 500;
            padding: 0.55rem 0.85rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.25rem;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .app-sidebar .nav-link:hover,
        .offcanvas-body .nav-link:hover {
            color: #ffffff;
            background-color: var(--theme-sidebar-hover);
        }

        .app-sidebar .nav-link.active,
        .offcanvas-body .nav-link.active {
            color: #ffffff !important;
            background-color: var(--theme-primary) !important;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .app-sidebar .nav-link i,
        .offcanvas-body .nav-link i {
            font-size: 1.05rem;
            width: 20px;
            display: inline-flex;
            justify-content: center;
        }

        /* Top Bar Styling */
        .topbar {
            background-color: transparent;
            padding: 0.5rem 0 1.25rem;
            margin-bottom: 1rem;
        }

        .user-profile-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #ffffff;
            padding: 0.35rem 0.75rem 0.35rem 0.4rem;
            border-radius: 9999px;
            border: 1px solid var(--theme-card-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            cursor: pointer;
            text-decoration: none;
            color: var(--theme-text-main);
        }

        .user-avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid var(--theme-card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            transition: all 0.15s ease;
        }
        .notification-btn:hover {
            color: var(--theme-primary);
            border-color: #a7f3d0;
        }
        .notification-btn .dot {
            position: absolute;
            top: 8px;
            right: 9px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #10b981;
            border: 1.5px solid #ffffff;
        }

        /* General Card Theme */
        .card {
            background-color: var(--theme-card-bg);
            border-radius: 16px;
            border: 1px solid var(--theme-card-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.15rem 1.5rem;
        }

        /* Buttons Theme */
        .btn-primary, .btn-orange {
            background-color: var(--theme-primary);
            border-color: var(--theme-primary);
            color: #ffffff;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.5rem 1.15rem;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
            transition: all 0.15s ease;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active,
        .btn-orange:hover, .btn-orange:focus, .btn-orange:active {
            background-color: var(--theme-primary-hover) !important;
            border-color: var(--theme-primary-dark) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }

        .btn-outline-primary {
            border-color: var(--theme-primary);
            color: var(--theme-primary);
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .btn-outline-primary:hover {
            background-color: var(--theme-primary);
            border-color: var(--theme-primary);
            color: #ffffff;
        }

        .btn-outline-secondary {
            border-color: #e2e8f0;
            color: #64748b;
            border-radius: 10px;
        }
        .btn-outline-secondary:hover {
            background-color: #f1f5f9;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        /* Forms Theme */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            padding: 0.55rem 0.85rem;
            font-size: 0.92rem;
            color: #1e293b;
            transition: all 0.15s ease-in-out;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.16);
            outline: 0;
        }

        /* Tables Theme */
        .table {
            color: var(--theme-text-main);
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #fcf9f5;
            color: #64748b;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #ede5da;
            padding: 12px 16px;
        }
        .table tbody td {
            vertical-align: middle;
            padding: 13px 16px;
            border-bottom: 1px solid #f3ece3;
            font-size: 0.9rem;
        }
        .table-hover tbody tr:hover {
            background-color: #fdfaf6;
        }

        /* Pagination Theme */
        .page-link {
            color: #4b5563;
            border-color: #e5ded4;
            border-radius: 8px;
            margin: 0 2px;
        }
        .page-link:hover {
            color: var(--theme-primary);
            background-color: #fff7ed;
            border-color: #fed7aa;
        }
        .page-item.active .page-link {
            background-color: var(--theme-primary);
            border-color: var(--theme-primary);
            color: #ffffff;
        }

        /* Badges Theme */
        .badge {
            font-weight: 600;
            padding: 0.35rem 0.65rem;
        }

        @media (max-width: 991.98px) {
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Left Desktop Sidebar (Fixed 250px Compact Width matching Reference) -->
        <aside class="app-sidebar d-none d-lg-flex">
            <!-- Branding: Nagarjuna KOT (Fixed at top) -->
            <a href="{{ route('dashboard') }}" class="sidebar-brand flex-shrink-0">
                <div class="sidebar-brand-icon">
                    <span>N</span>
                </div>
                <div class="sidebar-brand-text">
                    <h1>Nagarjuna KOT</h1>
                    <span>Restaurant Management</span>
                </div>
            </a>

            <!-- Nav Items List (Independently scrollable) -->
            <div class="sidebar-nav-scroll">
                @include('partials.sidebar-nav')
            </div>
        </aside>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel" style="background-color: #0c1e17 !important; max-width: 280px;">
            <div class="offcanvas-header border-bottom" style="border-color: rgba(255, 255, 255, 0.08) !important;">
                <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-brand-icon">
                        <span>N</span>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-white" id="sidebarOffcanvasLabel" style="font-size: 1rem;">Nagarjuna KOT</h5>
                        <span class="small text-muted" style="font-size: 0.72rem;">Restaurant Management</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-3">
                @include('partials.sidebar-nav')
            </div>
        </div>

        <!-- Main Content Area (Full Expansive Width) -->
        <main class="app-main">
            <!-- Topbar Header (Matching Dashcart Topbar) -->
            <header class="topbar d-flex align-items-center justify-content-between gap-3">
                    <!-- Mobile Hamburger Toggle -->
                    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                        <i class="bi bi-list fs-5"></i>
                    </button>

                    <!-- Search Input Pill (Exact Match to Image 1: Q Search orders, people, reports) -->
                    <div class="d-none d-md-flex align-items-center position-relative flex-grow-1" style="max-width: 440px;">
                        <i class="bi bi-search position-absolute text-muted" style="left: 16px; font-size: 0.85rem;"></i>
                        <input type="text" class="form-control rounded-pill ps-5 bg-white border-light-subtle shadow-none text-dark" placeholder="Search orders, people, reports" style="font-size: 0.85rem; height: 38px; border-color: #e2e8f0;">
                    </div>

                    <!-- Right Controls: Dark Mode Toggle, Notification & User Profile -->
                    <div class="d-flex align-items-center gap-2.5 ms-auto">
                        <!-- Dark Mode Toggle Icon (Matching Image 1) -->
                        <button type="button" class="notification-btn" title="Toggle theme">
                            <i class="bi bi-moon"></i>
                        </button>

                        <!-- Notification Bell (Matching Image 1) -->
                        <a href="{{ route('orders.index') }}" class="notification-btn" title="View Recent Orders">
                            <i class="bi bi-bell"></i>
                            <span class="dot"></span>
                        </a>

                        <!-- User Profile Dropdown -->
                        @php
                            $user = auth()->user();
                            $nameParts = explode(' ', trim($user->name));
                            $initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                            if (strlen($initials) === 1 && strlen($nameParts[0]) > 1) {
                                $initials = strtoupper(substr($nameParts[0], 0, 2));
                            }
                            $primaryRole = $user->roles->first()?->name ?? 'User';
                        @endphp

                        <div class="dropdown">
                            <a class="user-profile-badge dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar-circle">
                                    {{ $initials ?: 'SA' }}
                                </div>
                                <div class="text-start d-none d-sm-block me-1">
                                    <div class="fw-bold small text-dark" style="line-height: 1.1;">{{ $user->name }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $primaryRole }}</div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-2" style="border-radius: 12px; min-width: 220px;">
                                <li class="px-3 py-2 border-bottom mb-2">
                                    <div class="fw-bold text-dark small">{{ $user->name }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">{{ $user->email }}</div>
                                    <div class="mt-1">
                                        @foreach($user->roles as $r)
                                            <span class="badge bg-light text-dark border me-1 small" style="font-size: 0.68rem;">{{ $r->name }}</span>
                                        @endforeach
                                    </div>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded small py-2" href="{{ route('dashboard') }}">
                                        <i class="bi bi-grid-1x2 me-2 text-primary"></i>Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded small py-2" href="{{ route('kds.index') }}">
                                        <i class="bi bi-tv me-2 text-warning"></i>Kitchen TV Screens
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                                        @csrf
                                        <button class="dropdown-item rounded small py-2 text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </header>

                <!-- Page Alerts -->
                @include('partials.alerts')

                <!-- Module Content -->
                @yield('content')
            </main>
        </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
