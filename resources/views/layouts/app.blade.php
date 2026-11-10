<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') | Nagarjuna KOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; }
        .sidebar { min-height: calc(100vh - 56px); }
        .sidebar .nav-link {
            color: #495057;
            font-size: 0.92rem;
            padding: 0.55rem 0.85rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.15rem;
        }
        .sidebar .nav-link:hover {
            color: #0d6efd;
            background-color: #e9ecef;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
            font-weight: 500;
        }
        .sidebar-heading {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6c757d;
            font-weight: 700;
            padding: 0.75rem 0.85rem 0.35rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a class="navbar-brand fw-bold me-auto" href="{{ route('dashboard') }}">
                <i class="bi bi-fire text-warning me-1"></i>Nagarjuna KOT
            </a>

            <div class="d-flex align-items-center">
                <div class="text-white text-end me-3 d-none d-sm-block">
                    <div class="fw-semibold small">{{ auth()->user()->name }}</div>
                    <div class="text-light-50" style="font-size: 0.75rem;">
                        @foreach(auth()->user()->roles as $r)
                            <span class="badge bg-secondary me-1">{{ $r->name }}</span>
                        @endforeach
                        @if(auth()->user()->branch)
                            <span class="badge bg-info text-dark">{{ auth()->user()->branch->name }}</span>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button class="btn btn-outline-light btn-sm" title="Sign out">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign out
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Desktop Sidebar -->
            <aside class="col-lg-3 col-xl-2 d-none d-lg-block bg-white border-end sidebar p-3 shadow-sm">
                @include('partials.sidebar-nav')
            </aside>

            <!-- Mobile Offcanvas Sidebar -->
            <div class="offcanvas offcanvas-start bg-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
                <div class="offcanvas-header border-bottom">
                    <h5 class="offcanvas-title fw-bold" id="sidebarOffcanvasLabel">
                        <i class="bi bi-fire text-warning me-1"></i>Nagarjuna KOT
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-3">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="fw-bold">{{ auth()->user()->name }}</div>
                        <div class="text-muted small">{{ auth()->user()->email }}</div>
                        <div class="mt-1">
                            @foreach(auth()->user()->roles as $r)
                                <span class="badge bg-secondary me-1">{{ $r->name }}</span>
                            @endforeach
                            @if(auth()->user()->branch)
                                <span class="badge bg-info text-dark">{{ auth()->user()->branch->name }}</span>
                            @endif
                        </div>
                    </div>
                    @include('partials.sidebar-nav')
                </div>
            </div>

            <!-- Main Content Area -->
            <main class="col-lg-9 col-xl-10 ms-sm-auto px-md-4 py-4">
                @include('partials.alerts')
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
