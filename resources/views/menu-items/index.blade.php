@extends('layouts.app')

@section('title', 'Menu items')

@section('content')
    @include('partials.list-header', [
        'title' => 'Menu items',
        'permission' => 'menu-item.add',
        'createRoute' => 'menu-items.create',
        'createLabel' => 'Add menu item'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>CODE</th>
                        <th>BRANCH</th>
                        <th>CATEGORY</th>
                        <th>TYPE</th>
                        <th>PRICE</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($menuItems as $menuItem)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $menuItem->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $menuItem->code }}</span>
                            </td>
                            <td>{{ $menuItem->branch?->name ?? '—' }}</td>
                            <td>{{ $menuItem->category?->name ?? '—' }}</td>
                            <td>{{ $menuItem->menuType?->name ?? '—' }}</td>
                            <td class="fw-medium text-dark">₹{{ number_format((float) $menuItem->selling_price, 2) }}</td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $menuItem->is_active])
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button
                                        type="button"
                                        class="action-dots-btn"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        data-bs-popper-config='{"strategy":"fixed"}'
                                        title="Actions"
                                    >
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end action-dropdown-menu">
                                        @if(auth()->user()->hasPermission('menu-item.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('menu-items.edit', $menuItem) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('menu-item.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('menu-items.destroy', $menuItem) }}" class="m-0" onsubmit="return confirm('Delete this menu item?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash text-danger"></i>
                                                        <span>Delete</span>
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-cup-hot fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No menu items found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($menuItems->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $menuItems->firstItem() }} to {{ $menuItems->lastItem() }} of {{ $menuItems->total() }} records
                </div>
                <div>
                    {{ $menuItems->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
