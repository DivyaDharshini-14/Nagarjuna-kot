@extends('layouts.app')

@section('title', 'Menu types')

@section('content')
    @include('partials.list-header', ['title' => 'Menu types', 'permission' => 'menu-type.add', 'createRoute' => 'menu-types.create', 'createLabel' => 'Add menu type'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Company</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($menuTypes as $menuType)
                    <tr>
                        <td>{{ $menuType->name }}</td>
                        <td>{{ $menuType->company?->name }}</td>
                        <td>{{ $menuType->branch?->name ?? 'All branches' }}</td>
                        <td>@include('partials.status', ['active' => $menuType->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('menu-type.edit') && (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('company-admin') || $menuType->branch_id))
                                    <a href="{{ route('menu-types.edit', $menuType) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @if(auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('company-admin') || $menuType->branch_id)
                                    @include('partials.delete-button', ['permission' => 'menu-type.delete', 'route' => 'menu-types.destroy', 'model' => $menuType])
                                @endif
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No menu types found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $menuTypes->links() }}</div>
@endsection
