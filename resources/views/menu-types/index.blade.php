@extends('layouts.app')

@section('title', 'Menu types')

@section('content')
    @include('partials.list-header', ['title' => 'Menu types', 'permission' => 'menu-type.add', 'createRoute' => 'menu-types.create', 'createLabel' => 'Add menu type'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Company</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($menuTypes as $menuType)
                    <tr>
                        <td class="fw-medium">{{ $menuType->name }}</td>
                        <td>{{ $menuType->company?->name }}</td>
                        <td>@include('partials.status', ['active' => $menuType->is_active])</td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->hasPermission('menu-type.edit'))
                                <a href="{{ route('menu-types.edit', $menuType) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @if(auth()->user()->hasPermission('menu-type.delete'))
                                @include('partials.delete-button', ['permission' => 'menu-type.delete', 'route' => 'menu-types.destroy', 'model' => $menuType])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No menu types found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $menuTypes->links() }}</div>
@endsection
