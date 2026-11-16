@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    @include('partials.list-header', [
        'title' => 'Branches',
        'permission' => 'branch.add',
        'createRoute' => 'branches.create',
        'createLabel' => 'Add branch',
        'canCreate' => auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('company-admin'),
    ])

    @php
        $canEdit = auth()->user()->hasPermission('branch.edit');
        $canDelete = auth()->user()->hasPermission('branch.delete');
        $canAction = $canEdit || $canDelete;
    @endphp

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>CODE</th>
                        <th>PHONE</th>
                        <th class="text-center">STATUS</th>
                        @if($canAction)
                            <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $branch->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $branch->code }}</span>
                            </td>
                            <td><span class="text-muted">{{ $branch->phone ?? '—' }}</span></td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $branch->is_active])
                            </td>
                            @if($canAction)
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
                                            @if($canEdit)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('branches.edit', $branch) }}">
                                                        <i class="bi bi-pencil text-muted"></i>
                                                        <span>Edit</span>
                                                    </a>
                                                </li>
                                            @endif
                                            @if($canDelete)
                                                <li>
                                                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="m-0" onsubmit="return confirm('Delete this branch?')">
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
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canAction ? 5 : 4 }}" class="text-center text-muted py-5">
                                <i class="bi bi-geo-alt fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No branches found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($branches->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $branches->firstItem() }} to {{ $branches->lastItem() }} of {{ $branches->total() }} records
                </div>
                <div>
                    {{ $branches->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
