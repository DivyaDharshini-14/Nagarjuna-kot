@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Roles & Permissions</h1>
        @if(auth()->user()->hasPermission('role.add'))
            <a href="{{ route('roles.create') }}" class="btn btn-primary">Add role</a>
        @endif
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search role name or slug">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-outline-primary">Filter</button>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Role Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th class="text-center">Permissions</th>
                        <th class="text-center">Users</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>
                                <strong>{{ $role->name }}</strong>
                                @if(in_array($role->slug, ['admin', 'super-admin']))
                                    <span class="badge bg-danger ms-1">System Core</span>
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
                            <td>
                                @include('partials.status', ['active' => $role->is_active])
                            </td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('role.edit'))
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Edit & Permissions</a>
                                @endif
                                @if(!in_array($role->slug, ['admin', 'super-admin']))
                                    @include('partials.delete-button', ['permission' => 'role.delete', 'route' => 'roles.destroy', 'model' => $role])
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No roles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $roles->links() }}
    </div>
@endsection

