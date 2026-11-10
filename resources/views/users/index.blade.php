@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Users</h1>
        @if(auth()->user()->hasPermission('user.add'))
            <a href="{{ route('users.create') }}" class="btn btn-primary">Add user</a>
        @endif
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name, email, phone">
        </div>
        <div class="col-md-2">
            <select name="role" class="form-select">
                <option value="">All roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-outline-primary">Filter</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email & Phone</th>
                        <th>Company / Branch</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                @if($user->id === auth()->id())
                                    <span class="badge bg-info text-dark ms-1">You</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $user->email }}</div>
                                @if($user->phone)
                                    <small class="text-muted">{{ $user->phone }}</small>
                                @endif
                            </td>
                            <td>
                                <div>{{ $user->company?->name ?? 'System-wide' }}</div>
                                <small class="text-muted">{{ $user->branch?->name ?? 'All branches' }}</small>
                            </td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge bg-secondary">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted small">None</span>
                                @endforelse
                            </td>
                            <td>
                                @include('partials.status', ['active' => $user->is_active])
                            </td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('user.edit'))
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @if($user->id !== auth()->id())
                                    @include('partials.delete-button', ['permission' => 'user.delete', 'route' => 'users.destroy', 'model' => $user])
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
@endsection

