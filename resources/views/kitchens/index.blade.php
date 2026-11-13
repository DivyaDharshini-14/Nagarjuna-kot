@extends('layouts.app')

@section('title', 'Kitchens')

@section('content')
    @include('partials.list-header', [
        'title' => 'Kitchens',
        'permission' => 'kitchen.add',
        'createRoute' => 'kitchens.create',
        'createLabel' => 'Add kitchen'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>CODE</th>
                        <th>TYPE</th>
                        <th>BRANCH</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($kitchens as $kitchen)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $kitchen->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $kitchen->code }}</span>
                            </td>
                            <td>
                                @if($kitchen->kitchen_type)
                                    <span class="badge bg-light text-secondary border">{{ $kitchen->kitchen_type }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $kitchen->branch?->name ?? '—' }}</td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $kitchen->is_active])
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
                                        <li>
                                            <a class="dropdown-item" href="{{ ($kitchen->branch && $kitchen->branch->code ? url('/' . $kitchen->branch->code . '/' . $kitchen->code) : route('kds.screen', $kitchen)) }}" target="_blank">
                                                <i class="bi bi-tv text-warning"></i>
                                                <span>Open KDS (TV)</span>
                                            </a>
                                        </li>
                                        @if(auth()->user()->hasPermission('kitchen.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('kitchens.edit', $kitchen) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('kitchen.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('kitchens.destroy', $kitchen) }}" class="m-0" onsubmit="return confirm('Delete this kitchen?')">
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
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-fire fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No kitchens found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($kitchens->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $kitchens->firstItem() }} to {{ $kitchens->lastItem() }} of {{ $kitchens->total() }} records
                </div>
                <div>
                    {{ $kitchens->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
