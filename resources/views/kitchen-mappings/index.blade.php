@extends('layouts.app')

@section('title', 'Kitchen mapping')

@section('content')
    @include('partials.list-header', ['title' => 'Kitchen mapping', 'permission' => 'kitchen-mapping.add', 'createRoute' => 'kitchen-mappings.create', 'createLabel' => 'Add mapping'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Menu item</th><th>Kitchen station</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($mappings as $mapping)
                    <tr>
                        <td class="fw-medium">
                            {{ $mapping->menuItem?->name }}
                            @if($mapping->menuItem?->code)
                                <span class="text-muted small">({{ $mapping->menuItem->code }})</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-fire text-warning me-1"></i>{{ $mapping->kitchen?->name }}
                            </span>
                        </td>
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
