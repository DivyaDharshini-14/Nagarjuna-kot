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

    <!-- Unified Tabletop Theme -->
    <link href="{{ asset('css/theme.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
    <div class="app-layout">
        <!-- Left Desktop Sidebar (Fixed 255px Compact Width matching Tabletop Reference) -->
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
                @include('partials.sidebar-nav', ['navContext' => 'desktop'])
            </div>
        </aside>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start bg-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel" style="max-width: 280px;">
            <div class="offcanvas-header border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-brand-icon">
                        <span>N</span>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" id="sidebarOffcanvasLabel" style="font-size: 1rem;">Nagarjuna KOT</h5>
                        <span class="small text-muted" style="font-size: 0.72rem;">Restaurant Management</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-3">
                @include('partials.sidebar-nav', ['navContext' => 'mobile'])
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="app-main">
            <!-- Topbar Header -->
            <header class="topbar d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <!-- Mobile Hamburger Toggle -->
                    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                </div>

                <!-- Right Controls: Notification & User Profile -->
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- Notification Bell -->
                    <a href="{{ route('orders.index') }}" class="topbar-icon-btn" title="View Recent Orders">
                        <i class="bi bi-bell"></i>
                        <span class="badge-dot"></span>
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
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-2" style="border-radius: 12px; min-width: 220px;">
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
