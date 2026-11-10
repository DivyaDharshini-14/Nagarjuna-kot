@extends('layouts.app')

@section('title', 'Kitchen mapping')

@section('content')
    @include('partials.list-header', ['title' => 'Kitchen mapping', 'permission' => 'kitchen-mapping.add', 'createRoute' => 'kitchen-mappings.create', 'createLabel' => 'Add mapping'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Menu item</th><th>Kitchen</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($mappings as $mapping)
                    <tr>
                        <td>{{ $mapping->menuItem?->name }}</td>
                        <td>{{ $mapping->kitchen?->name }}</td>
                        <td>{{ $mapping->branch?->name }}</td>
                        <td>@include('partials.status', ['active' => $mapping->is_active])</td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->hasPermission('kitchen-mapping.edit'))
                                <a href="{{ route('kitchen-mappings.edit', $mapping) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @include('partials.delete-button', ['permission' => 'kitchen-mapping.delete', 'route' => 'kitchen-mappings.destroy', 'model' => $mapping])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No kitchen mappings found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $mappings->links() }}</div>
@endsection
