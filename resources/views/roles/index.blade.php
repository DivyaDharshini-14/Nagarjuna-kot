@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 fw-bold text-dark mb-0">Roles & Permissions</h1>
        @if(auth()->user()->hasPermission('role.add'))
            <a href="{{ route('roles.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Add role</span>
            </a>
        @endif
    </div>

    <div class="card tabletop-card border-0 shadow-sm mb-3 p-3 bg-white">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: #e2e8f0;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control border-start-0 ps-0"
                        placeholder="Search role name or slug..."
                        style="border-radius: 0 8px 8px 0; border-color: #e2e8f0;"
                    >
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->filled('search') || request()->filled('status'))
                    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ROLE NAME</th>
                        <th>SLUG</th>
                        <th>DESCRIPTION</th>
                        <th class="text-center">PERMISSIONS</th>
                        <th class="text-center">USERS</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td class="ps-4">
                                <strong class="text-dark">{{ $role->name }}</strong>
                                @if(in_array($role->slug, ['admin', 'super-admin']))
                                    <span class="badge bg-danger ms-1" style="font-size: 0.68rem;">System Core</span>
                                @endif
                            </td>
                            <td><code>{{ $role->slug }}</code></td>
                            <td>
                                <small class="text-muted">{{ $role->description ?? '—' }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $role->permissions_count }} permissions
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    {{ $role->users_count }} users
                                </span>
                            </td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $role->is_active])
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
                                        @if(auth()->user()->hasPermission('role.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('roles.edit', $role) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit & Permissions</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(!in_array($role->slug, ['admin', 'super-admin']) && auth()->user()->hasPermission('role.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('roles.destroy', $role) }}" class="m-0" onsubmit="return confirm('Delete this role?')">
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
                                <i class="bi bi-shield-lock fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No roles found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($roles->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $roles->firstItem() }} to {{ $roles->lastItem() }} of {{ $roles->total() }} records
                </div>
                <div>
                    {{ $roles->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
