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

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->code }}</td>
                            <td>{{ $company->email }}</td>
                            <td>{{ $company->phone }}</td>
                            <td>@include('partials.status', ['active' => $company->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('company.edit'))
                                    <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @include('partials.delete-button', ['permission' => 'company.delete', 'route' => 'companies.destroy', 'model' => $company])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No companies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $companies->links() }}</div>
@endsection
