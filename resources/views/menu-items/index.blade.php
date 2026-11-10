@extends('layouts.app')

@section('title', 'Menu items')

@section('content')
    @include('partials.list-header', ['title' => 'Menu items', 'permission' => 'menu-item.add', 'createRoute' => 'menu-items.create', 'createLabel' => 'Add menu item'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Branch</th><th>Category</th><th>Type</th><th>Price</th><th>Availability</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($menuItems as $menuItem)
                    <tr>
                        <td>{{ $menuItem->name }}</td>
                        <td>{{ $menuItem->code }}</td>
                        <td>{{ $menuItem->branch?->name }}</td>
                        <td>{{ $menuItem->category?->name }}</td>
                        <td>{{ $menuItem->menuType?->name }}</td>
                        <td>{{ number_format((float) $menuItem->selling_price, 2) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $menuItem->availability)) }}</td>
                        <td>@include('partials.status', ['active' => $menuItem->is_active])</td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->hasPermission('menu-item.edit'))
                                <a href="{{ route('menu-items.edit', $menuItem) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @include('partials.delete-button', ['permission' => 'menu-item.delete', 'route' => 'menu-items.destroy', 'model' => $menuItem])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No menu items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $menuItems->links() }}</div>
@endsection
