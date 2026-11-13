@extends('layouts.app')

@section('title', 'Kitchen mapping')

@section('content')
    @include('partials.list-header', [
        'title' => 'Kitchen mapping',
        'permission' => 'kitchen-mapping.add',
        'createRoute' => 'kitchen-mappings.create',
        'createLabel' => 'Add mapping'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">MENU ITEM</th>
                        <th>KITCHEN STATION</th>
                        <th>BRANCH</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mappings as $mapping)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">
                                {{ $mapping->menuItem?->name ?? '—' }}
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-fire text-warning me-1"></i>{{ $mapping->kitchen?->name ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $mapping->branch?->name ?? '—' }}</td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $mapping->is_active])
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
                                        @if(auth()->user()->hasPermission('kitchen-mapping.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('kitchen-mappings.edit', $mapping) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('kitchen-mapping.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('kitchen-mappings.destroy', $mapping) }}" class="m-0" onsubmit="return confirm('Delete this kitchen mapping?')">
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
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-bezier2 fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No kitchen mappings found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($mappings->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $mappings->firstItem() }} to {{ $mappings->lastItem() }} of {{ $mappings->total() }} records
                </div>
                <div>
                    {{ $mappings->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
