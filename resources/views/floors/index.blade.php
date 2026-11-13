@extends('layouts.app')

@section('title', 'Floors')

@section('content')
    @include('partials.list-header', [
        'title' => 'Floors',
        'permission' => 'floor.add',
        'createRoute' => 'floors.create',
        'createLabel' => 'Add floor'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>BRANCH</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($floors as $floor)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $floor->name }}</td>
                            <td>{{ $floor->branch?->name ?? '—' }}</td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $floor->is_active])
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
                                        @if(auth()->user()->hasPermission('floor.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('floors.edit', $floor) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('floor.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('floors.destroy', $floor) }}" class="m-0" onsubmit="return confirm('Delete this floor?')">
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
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-layers fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No floors found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($floors->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $floors->firstItem() }} to {{ $floors->lastItem() }} of {{ $floors->total() }} records
                </div>
                <div>
                    {{ $floors->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
