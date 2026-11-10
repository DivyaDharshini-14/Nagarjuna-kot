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
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Name</th><th>Code</th><th>Company</th><th>Phone</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->code }}</td>
                            <td>{{ $branch->company?->name }}</td>
                            <td>{{ $branch->phone }}</td>
                            <td>@include('partials.status', ['active' => $branch->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('branch.edit'))
                                    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @include('partials.delete-button', ['permission' => 'branch.delete', 'route' => 'branches.destroy', 'model' => $branch])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No branches found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $branches->links() }}</div>
@endsection
