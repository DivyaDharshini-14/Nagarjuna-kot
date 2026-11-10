@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    @include('partials.list-header', ['title' => 'Categories', 'permission' => 'category.add', 'createRoute' => 'categories.create', 'createLabel' => 'Add category'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Company</th><th>Branch</th><th>Order</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->code }}</td>
                        <td>{{ $category->company?->name }}</td>
                        <td>{{ $category->branch?->name ?? 'All branches' }}</td>
                        <td>{{ $category->display_order }}</td>
                        <td>@include('partials.status', ['active' => $category->is_active])</td>
                            <td class="text-end text-nowrap">
                                @if(auth()->user()->hasPermission('category.edit') && (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('company-admin') || $category->branch_id))
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @if(auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('company-admin') || $category->branch_id)
                                    @include('partials.delete-button', ['permission' => 'category.delete', 'route' => 'categories.destroy', 'model' => $category])
                                @endif
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $categories->links() }}</div>
@endsection
