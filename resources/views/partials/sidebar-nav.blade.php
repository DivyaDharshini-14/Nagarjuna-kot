<ul class="nav flex-column mb-auto pb-4">
    <li class="sidebar-heading">Main</li>
    {{-- Dashboard --}}
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
    </li>

    @if(auth()->user()->hasPermission('report.view'))
        @php
            $isReportsActive = request()->routeIs('reports.*');
            $reportsCollapseId = 'reportsSubmenu_' . ($navContext ?? 'desktop');
            $canViewReportAnalytics = !auth()->user()->isWaiter()
                && !auth()->user()->isKitchenStaff()
                && (auth()->user()->isAdmin() || auth()->user()->isManager());
        @endphp
        <li class="nav-item">
            <a class="nav-link {{ $isReportsActive ? 'active active-parent' : 'collapsed' }}"
               data-bs-toggle="collapse"
               href="#{{ $reportsCollapseId }}"
               role="button"
               aria-expanded="{{ $isReportsActive ? 'true' : 'false' }}"
               aria-controls="{{ $reportsCollapseId }}">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
                <i class="bi bi-chevron-down ms-auto sidebar-chevron"></i>
            </a>
            <div class="collapse {{ $isReportsActive ? 'show' : '' }}" id="{{ $reportsCollapseId }}">
                <ul class="nav flex-column sidebar-submenu">
                    <li class="nav-item">
                        <a class="nav-link submenu-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                            <i class="bi bi-clipboard-data"></i>
                            <span>Operational Reports</span>
                        </a>
                    </li>
                    @if($canViewReportAnalytics && auth()->user()->hasPermission('order.view'))
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('reports.orders*') ? 'active' : '' }}" href="{{ route('reports.orders') }}">
                                <i class="bi bi-file-earmark-bar-graph"></i>
                                <span>Order Report</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('reports.menu-items*') ? 'active' : '' }}" href="{{ route('reports.menu-items') }}">
                                <i class="bi bi-card-checklist"></i>
                                <span>Menu Items Report</span>
                            </a>
                        </li>
                    @endif
                    @if($canViewReportAnalytics && auth()->user()->hasPermission('kot.view'))
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('reports.kots*') ? 'active' : '' }}" href="{{ route('reports.kots') }}">
                                <i class="bi bi-receipt-cutoff"></i>
                                <span>KOT Report</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </li>
    @endif

    {{-- Kitchen Staff Specific Operations --}}
    @if(auth()->user()->isKitchenStaff())
        <li class="sidebar-heading mt-3">Kitchen Operations</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kds.*') ? 'active' : '' }}" href="{{ route('kds.index') }}">
                <i class="bi bi-display"></i>
                <span>Kitchen Display (KDS)</span>
            </a>
        </li>
        @if(auth()->user()->hasPermission('menu-item.view'))
            @php
                $isStockActive = request()->routeIs('stock.*');
                $stockCollapseId = 'stockSubmenu_' . ($navContext ?? 'desktop');
            @endphp
            {{-- Stock Management Expandable Parent Menu --}}
            <li class="nav-item">
                <a class="nav-link {{ $isStockActive ? 'active active-parent' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#{{ $stockCollapseId }}"
                   role="button"
                   aria-expanded="{{ $isStockActive ? 'true' : 'false' }}"
                   aria-controls="{{ $stockCollapseId }}">
                    <i class="bi bi-boxes"></i>
                    <span>Stock Management</span>
                    <i class="bi bi-chevron-down ms-auto sidebar-chevron"></i>
                </a>
                <div class="collapse {{ $isStockActive ? 'show' : '' }}" id="{{ $stockCollapseId }}">
                    <ul class="nav flex-column sidebar-submenu">
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('stock.index') || request()->routeIs('stock.stock-in') ? 'active' : '' }}" href="{{ route('stock.stock-in') }}">
                                <i class="bi bi-box-arrow-in-down"></i>
                                <span>Kitchen Stock</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('stock.adjustments') ? 'active' : '' }}" href="{{ route('stock.adjustments') }}">
                                <i class="bi bi-sliders"></i>
                                <span>Stock Adjustment</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu-link {{ request()->routeIs('stock.history') ? 'active' : '' }}" href="{{ route('stock.history') }}">
                                <i class="bi bi-clock-history"></i>
                                <span>Stock Transaction History</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        @endif
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('kots.*') ? 'active' : '' }}" href="{{ route('kots.index') }}">
                <i class="bi bi-card-checklist"></i>
                <span>KOT Tickets</span>
            </a>
        </li>
        @if(auth()->user()->branch_id)
            @php
                $kitchenList = auth()->user()->kitchens()->with('branch')
                    ->where('kitchens.company_id', auth()->user()->company_id)
                    ->where('kitchens.branch_id', auth()->user()->branch_id)
                    ->where('kitchens.is_active', true)->get();
            @endphp
            @foreach($kitchenList as $k)
                <li class="nav-item">
                    <a class="nav-link"
                       href="{{ ($k->branch && $k->branch->code ? url('/' . $k->branch->code . '/' . $k->code) : route('kots.display', $k)) }}"
                       target="_blank">
                        <i class="bi bi-tv"></i>
                        <span>{{ $k->name }} (TV)</span>
                    </a>
                </li>
            @endforeach
        @endif
    @else
        {{-- Live Operations for Waiter / Manager / Admin --}}
        @if(auth()->user()->hasPermission('order.approve') || auth()->user()->hasPermission('kot.view') || auth()->user()->hasPermission('menu-item.view'))
            @if(auth()->user()->hasPermission('order.approve'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('cancellations.*') ? 'active' : '' }}" href="{{ route('cancellations.index') }}">
                        <i class="bi bi-exclamation-octagon"></i>
                        <span>Cancellation Requests</span>
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

            @if(auth()->user()->hasPermission('menu-item.view'))
                @php
                    $isStockActive = request()->routeIs('stock.*');
                    $stockCollapseId = 'stockSubmenu_' . ($navContext ?? 'desktop');
                @endphp
                {{-- Stock Management Expandable Parent Menu --}}
                <li class="nav-item">
                    <a class="nav-link {{ $isStockActive ? 'active active-parent' : 'collapsed' }}"
                       data-bs-toggle="collapse"
                       href="#{{ $stockCollapseId }}"
                       role="button"
                       aria-expanded="{{ $isStockActive ? 'true' : 'false' }}"
                       aria-controls="{{ $stockCollapseId }}">
                        <i class="bi bi-boxes"></i>
                        <span>Stock Management</span>
                        <i class="bi bi-chevron-down ms-auto sidebar-chevron"></i>
                    </a>
                    <div class="collapse {{ $isStockActive ? 'show' : '' }}" id="{{ $stockCollapseId }}">
                        <ul class="nav flex-column sidebar-submenu">
                            <li class="nav-item">
                                <a class="nav-link submenu-link {{ request()->routeIs('stock.index') || request()->routeIs('stock.stock-in') ? 'active' : '' }}" href="{{ route('stock.stock-in') }}">
                                    <i class="bi bi-box-arrow-in-down"></i>
                                    <span>Kitchen Stock</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link submenu-link {{ request()->routeIs('stock.adjustments') ? 'active' : '' }}" href="{{ route('stock.adjustments') }}">
                                    <i class="bi bi-sliders"></i>
                                    <span>Stock Adjustment</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link submenu-link {{ request()->routeIs('stock.history') ? 'active' : '' }}" href="{{ route('stock.history') }}">
                                    <i class="bi bi-clock-history"></i>
                                    <span>Stock Transaction History</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
        @endif

        {{-- Masters Section Expandable Parent Menu --}}
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
            $isMastersActive = collect($masters)->contains(fn ($m) => request()->routeIs($m[1]));
            $mastersCollapseId = 'mastersSubmenu_' . ($navContext ?? 'desktop');
        @endphp

        @if($hasAnyMaster)
            <li class="nav-item">
                <a class="nav-link {{ $isMastersActive ? 'active active-parent' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#{{ $mastersCollapseId }}"
                   role="button"
                   aria-expanded="{{ $isMastersActive ? 'true' : 'false' }}"
                   aria-controls="{{ $mastersCollapseId }}">
                    <i class="bi bi-stack"></i>
                    <span>Masters</span>
                    <i class="bi bi-chevron-down ms-auto sidebar-chevron"></i>
                </a>
                <div class="collapse {{ $isMastersActive ? 'show' : '' }}" id="{{ $mastersCollapseId }}">
                    <ul class="nav flex-column sidebar-submenu">
                        @foreach($masters as [$permission, $routePattern, $routeName, $label, $icon])
                            @if(auth()->user()->hasPermission($permission))
                                <li class="nav-item">
                                    <a class="nav-link submenu-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                                        <i class="bi {{ $icon }}"></i>
                                        <span>{{ $label }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </li>
        @endif

        {{-- Users & Access Section Expandable Parent Menu --}}
        @if(auth()->user()->isAdmin())
            @php
                $isUsersActive = request()->routeIs('users.*') || request()->routeIs('roles.*');
                $usersCollapseId = 'usersSubmenu_' . ($navContext ?? 'desktop');
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ $isUsersActive ? 'active active-parent' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#{{ $usersCollapseId }}"
                   role="button"
                   aria-expanded="{{ $isUsersActive ? 'true' : 'false' }}"
                   aria-controls="{{ $usersCollapseId }}">
                    <i class="bi bi-people"></i>
                    <span>Users &amp; Access</span>
                    <i class="bi bi-chevron-down ms-auto sidebar-chevron"></i>
                </a>
                <div class="collapse {{ $isUsersActive ? 'show' : '' }}" id="{{ $usersCollapseId }}">
                    <ul class="nav flex-column sidebar-submenu">
                        @if(auth()->user()->hasPermission('user.view'))
                            <li class="nav-item">
                                <a class="nav-link submenu-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                    <i class="bi bi-person"></i>
                                    <span>Users</span>
                                </a>
                            </li>
                        @endif
                        @if(auth()->user()->hasPermission('role.view'))
                            <li class="nav-item">
                                <a class="nav-link submenu-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                                    <i class="bi bi-shield-lock"></i>
                                    <span>Roles &amp; Access</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </li>
        @elseif(auth()->user()->isManager() && auth()->user()->hasPermission('user.view'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i>
                    <span>Branch Staff</span>
                </a>
            </li>
        @endif
    @endif
    @if(auth()->user()->hasPermission('settings.view'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i class="bi bi-gear"></i><span>Branch settings</span></a></li>
    @endif
</ul>
