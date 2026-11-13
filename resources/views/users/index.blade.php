@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="users-page-wrapper">
    {{-- Page Heading --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-0">Users</h1>
        </div>
    </div>

    {{-- Clean Container Card (Tabletop Style) --}}
    <div class="card tabletop-card border-0 shadow-sm mb-4">
        {{-- Card Header: Title on Left, Add User Button on Right --}}
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark" style="font-size: 1rem;">User List</span>
            @if(auth()->user()->hasPermission('user.add'))
                <a href="{{ route('users.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add User</span>
                </a>
            @endif
        </div>

        {{-- Table Container --}}
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center ps-4" style="width: 50px;">#</th>
                        <th>EMP CODE</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>MOBILE NUMBER</th>
                        <th>DESIGNATION</th>
                        <th>BRANCH</th>
                        <th>ROLE</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            {{-- 1. S.No --}}
                            <td class="text-center ps-4 text-muted fw-medium" style="font-size: 0.82rem;">
                                {{ $users->firstItem() + $loop->index }}
                            </td>

                            {{-- 2. Emp Code --}}
                            <td>
                                @if($user->employee_code)
                                    <span class="fw-medium text-dark font-monospace" style="font-size: 0.85rem;">{{ $user->employee_code }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- 3. Name --}}
                            <td>
                                <span class="fw-semibold text-dark">{{ $user->name }}</span>
                                @if($user->id === auth()->id())
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1" style="font-size: 0.68rem;">You</span>
                                @endif
                            </td>

                            {{-- 4. Email --}}
                            <td>
                                <span class="text-muted">{{ $user->email }}</span>
                            </td>

                            {{-- 5. Mobile Number --}}
                            <td>
                                <span class="text-muted">{{ $user->phone ?? '—' }}</span>
                            </td>

                            {{-- 6. Designation --}}
                            <td>
                                <span class="text-muted">{{ $user->designation ?? '—' }}</span>
                            </td>

                            {{-- 7. Branch --}}
                            <td>
                                <span>{{ $user->branch?->name ?? 'All branches' }}</span>
                            </td>

                            {{-- 8. Role --}}
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge bg-light text-secondary border me-1">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>

                            {{-- 9. Status --}}
                            <td class="text-center">
                                @include('partials.status', ['active' => $user->is_active])
                            </td>

                            {{-- 10. Actions (⋮ Dropdown) --}}
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
                                        @if(auth()->user()->hasPermission('user.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('users.edit', $user) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if($user->id !== auth()->id() && auth()->user()->hasPermission('user.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="m-0" onsubmit="return confirm('Are you sure you want to delete this user?')">
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
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Container --}}
        @if($users->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} users
                </div>
                <div>
                    {{ $users->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
