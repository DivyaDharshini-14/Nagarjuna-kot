@extends('layouts.app')

@section('title', 'Tables')

@section('content')
    @include('partials.list-header', [
        'title' => 'Tables',
        'permission' => 'table.add',
        'createRoute' => 'tables.create',
        'createLabel' => 'Add table'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>BRANCH</th>
                        <th>FLOOR</th>
                        <th>SEATS</th>
                        <th>TABLE STATUS</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tables as $table)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $table->name }}</td>
                            <td>{{ $table->branch?->name ?? '—' }}</td>
                            <td>{{ $table->floor?->name ?? '—' }}</td>
                            <td>{{ $table->seating_capacity }}</td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    {{ ucfirst($table->table_status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $table->is_active])
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
                                        @if(auth()->user()->hasPermission('table.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('tables.edit', $table) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('table.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('tables.destroy', $table) }}" class="m-0" onsubmit="return confirm('Delete this table?')">
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
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-grid-3x3 fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No tables found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tables->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $tables->firstItem() }} to {{ $tables->lastItem() }} of {{ $tables->total() }} records
                </div>
                <div>
                    {{ $tables->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
