<ul class="nav flex-column mb-auto">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>

    {{-- 1. Kitchen Staff: Kitchen Display & KOT Status Actions --}}
    @if(auth()->user()->isKitchenStaff())
        <li class="sidebar-heading mt-3">Kitchen Operations</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kds.*') ? 'active' : '' }}" href="{{ route('kds.index') }}">
                <i class="bi bi-tv"></i> Kitchen Displays (KDS)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kots.*') ? 'active' : '' }}" href="{{ route('kots.index') }}">
                <i class="bi bi-egg-fried"></i> KOT Tickets
            </a>
        </li>
        @if(auth()->user()->branch_id)
            @php
                $kitchenList = \App\Models\Kitchen::where('branch_id', auth()->user()->branch_id)->where('is_active', true)->get();
            @endphp
            @foreach($kitchenList as $k)
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('kots.display', $k) }}" target="_blank">
                        <i class="bi bi-tv"></i> {{ $k->name }} (TV)
                    </a>
                </li>
            @endforeach
        @endif
    @else
        {{-- 2. Waiter (Order Management) / Manager (Order & KOT Monitoring) / Admin (Full) --}}
        @if(auth()->user()->hasPermission('order.view') || auth()->user()->hasPermission('kot.view'))
            <li class="sidebar-heading mt-3">
                @if(auth()->user()->isWaiter())
                    Order Management
                @elseif(auth()->user()->isManager())
                    Order & KOT Monitoring
                @else
                    Live Operations
                @endif
            </li>

            @if(auth()->user()->hasPermission('order.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                        <i class="bi bi-receipt"></i> Orders
                    </a>
                </li>
            @endif

            @if(!auth()->user()->isWaiter() && auth()->user()->hasPermission('kot.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('kds.*') ? 'active' : '' }}" href="{{ route('kds.index') }}">
                        <i class="bi bi-tv"></i> Kitchen Displays (KDS)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('kots.*') ? 'active' : '' }}" href="{{ route('kots.index') }}">
                        <i class="bi bi-egg-fried"></i> KOT Monitoring
                    </a>
                </li>
            @endif
        @endif

        {{-- 3. Staff & Access Control (Admin / Manager) --}}
        @if(auth()->user()->isAdmin())
            <li class="sidebar-heading mt-3">Access Control</li>
            @if(auth()->user()->hasPermission('user.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="bi bi-people"></i> Users
                    </a>
                </li>
            @endif
            @if(auth()->user()->hasPermission('role.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                        <i class="bi bi-shield-lock"></i> Roles & Permissions
                    </a>
                </li>
            @endif
        @elseif(auth()->user()->isManager() && auth()->user()->hasPermission('user.view'))
            <li class="sidebar-heading mt-3">Staff Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i> Branch Staff
                </a>
            </li>
        @endif

        {{-- 4. Restaurant Masters / Floor Operations --}}
        @php
            $masters = [
                ['company.view', 'companies.*', 'companies.index', 'Companies', 'bi-building'],
                ['branch.view', 'branches.*', 'branches.index', 'Branches', 'bi-geo-alt'],
                ['kitchen.view', 'kitchens.*', 'kitchens.index', 'Kitchens', 'bi-egg-fried'],
                ['floor.view', 'floors.*', 'floors.index', 'Floors', 'bi-layers'],
                ['table.view', 'tables.*', 'tables.index', 'Tables', 'bi-grid-3x3-gap'],
                ['category.view', 'categories.*', 'categories.index', 'Categories', 'bi-tags'],
                ['menu-type.view', 'menu-types.*', 'menu-types.index', 'Menu Types', 'bi-bookmark'],
                ['menu-item.view', 'menu-items.*', 'menu-items.index', 'Menu Items', 'bi-cup-hot'],
                ['kitchen-mapping.view', 'kitchen-mappings.*', 'kitchen-mappings.index', 'Kitchen Mapping', 'bi-arrow-left-right'],
            ];

            $hasAnyMaster = collect($masters)->contains(fn ($m) => auth()->user()->hasPermission($m[0]));
        @endphp

        @if($hasAnyMaster)
            <li class="sidebar-heading mt-3">
                @if(auth()->user()->isWaiter())
                    Floor & Menu
                @else
                    Restaurant Masters
                @endif
            </li>
            @foreach($masters as [$permission, $routePattern, $routeName, $label, $icon])
                @if(auth()->user()->hasPermission($permission))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                            <i class="bi {{ $icon }}"></i> {{ $label }}
                        </a>
                    </li>
                @endif
            @endforeach
        @endif
    @endif
</ul>
