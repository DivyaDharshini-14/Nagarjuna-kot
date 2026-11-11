<ul class="nav flex-column mb-auto">
    {{-- Dashboard --}}
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
    </li>

    {{-- Kitchen Staff Specific Operations --}}
    @if(auth()->user()->isKitchenStaff())
        <li class="sidebar-heading mt-3">Kitchen Operations</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kds.*') ? 'active' : '' }}" href="{{ route('kds.index') }}">
                <i class="bi bi-display"></i>
                <span>Kitchen Display (KDS)</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kots.*') ? 'active' : '' }}" href="{{ route('kots.index') }}">
                <i class="bi bi-card-checklist"></i>
                <span>KOT Tickets</span>
            </a>
        </li>
        @if(auth()->user()->branch_id)
            @php
                $kitchenList = \App\Models\Kitchen::where('branch_id', auth()->user()->branch_id)->where('is_active', true)->get();
            @endphp
            @foreach($kitchenList as $k)
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('kots.display', $k) }}" target="_blank">
                        <i class="bi bi-tv"></i>
                        <span>{{ $k->name }} (TV)</span>
                    </a>
                </li>
            @endforeach
        @endif
    @else
        {{-- Live Operations for Waiter / Manager / Admin --}}
        @if(auth()->user()->hasPermission('order.view') || auth()->user()->hasPermission('kot.view'))
            @if(auth()->user()->hasPermission('order.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                        <i class="bi bi-receipt"></i>
                        <span>Orders</span>
                    </a>
                </li>
            @endif

            @if(!auth()->user()->isWaiter() && auth()->user()->hasPermission('kot.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('kds.*') ? 'active' : '' }}" href="{{ route('kds.index') }}">
                        <i class="bi bi-display"></i>
                        <span>Kitchen Display (KDS)</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('kots.*') ? 'active' : '' }}" href="{{ route('kots.index') }}">
                        <i class="bi bi-clock-history"></i>
                        <span>KOT Monitoring</span>
                    </a>
                </li>
            @endif
        @endif

        {{-- Masters Section (Matching Reference: Company, Branch, Kitchen, Floor & Table, Category, Menu Type, Menu Items, Kitchen Mapping) --}}
        @php
            $masters = [
                ['company.view', 'companies.*', 'companies.index', 'Company', 'bi-building'],
                ['branch.view', 'branches.*', 'branches.index', 'Branch', 'bi-geo-alt'],
                ['kitchen.view', 'kitchens.*', 'kitchens.index', 'Kitchen', 'bi-fire'],
                ['floor.view', 'floors.*', 'floors.index', 'Floor', 'bi-layers'],
                ['table.view', 'tables.*', 'tables.index', 'Table', 'bi-grid-3x3'],
                ['category.view', 'categories.*', 'categories.index', 'Category', 'bi-stack'],
                ['menu-type.view', 'menu-types.*', 'menu-types.index', 'Menu Type', 'bi-bookmark'],
                ['menu-item.view', 'menu-items.*', 'menu-items.index', 'Menu Items', 'bi-cup-hot'],
                ['kitchen-mapping.view', 'kitchen-mappings.*', 'kitchen-mappings.index', 'Kitchen Mapping', 'bi-bezier2'],
            ];

            $hasAnyMaster = collect($masters)->contains(fn ($m) => auth()->user()->hasPermission($m[0]));
        @endphp

        @if($hasAnyMaster)
            <li class="sidebar-heading mt-3">Masters</li>
            @foreach($masters as [$permission, $routePattern, $routeName, $label, $icon])
                @if(auth()->user()->hasPermission($permission))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                            <i class="bi {{ $icon }}"></i>
                            <span>{{ $label }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        @endif

        {{-- Users & Access Section (Matching Reference: Users & Access) --}}
        @if(auth()->user()->isAdmin())
            <li class="sidebar-heading mt-3">Users &amp; Access</li>
            @if(auth()->user()->hasPermission('user.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                    </a>
                </li>
            @endif
            @if(auth()->user()->hasPermission('role.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                        <i class="bi bi-shield-lock"></i>
                        <span>Roles &amp; Access</span>
                    </a>
                </li>
            @endif
        @elseif(auth()->user()->isManager() && auth()->user()->hasPermission('user.view'))
            <li class="sidebar-heading mt-3">Staff Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i>
                    <span>Branch Staff</span>
                </a>
            </li>
        @endif
    @endif
</ul>
