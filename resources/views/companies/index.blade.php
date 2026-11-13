@extends('layouts.app')

@section('title', 'Companies')

@section('content')
    @include('partials.list-header', [
        'title' => 'Companies',
        'permission' => 'company.add',
        'createRoute' => 'companies.create',
        'createLabel' => 'Add company',
        'canCreate' => auth()->user()->hasRole('super-admin'),
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>CODE</th>
                        <th>EMAIL</th>
                        <th>PHONE</th>
                        <th>GST NUMBER</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $company->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $company->code }}</span>
                            </td>
                            <td><span class="text-muted">{{ $company->email ?? '—' }}</span></td>
                            <td><span class="text-muted">{{ $company->phone ?? '—' }}</span></td>
                            <td>
                                <span class="badge bg-light text-secondary border font-monospace small">
                                    {{ $company->gst_number ?? '—' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $company->is_active])
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
                                        @if(auth()->user()->hasPermission('company.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('companies.edit', $company) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('company.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('companies.destroy', $company) }}" class="m-0" onsubmit="return confirm('Delete this company?')">
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
                                <i class="bi bi-building fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No companies found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($companies->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $companies->firstItem() }} to {{ $companies->lastItem() }} of {{ $companies->total() }} records
                </div>
                <div>
                    {{ $companies->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
