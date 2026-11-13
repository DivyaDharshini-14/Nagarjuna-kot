@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    @include('partials.list-header', [
        'title' => 'Categories',
        'permission' => 'category.add',
        'createRoute' => 'categories.create',
        'createLabel' => 'Add category'
    ])

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>CODE</th>
                        <th>ORDER</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="ps-4 fw-medium text-dark">{{ $category->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $category->code }}</span>
                            </td>
                            <td class="text-muted">{{ $category->display_order }}</td>
                            <td class="text-center">
                                @include('partials.status', ['active' => $category->is_active])
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
                                        @if(auth()->user()->hasPermission('category.edit'))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('categories.edit', $category) }}">
                                                    <i class="bi bi-pencil text-muted"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                        @endif
                                        @if(auth()->user()->hasPermission('category.delete'))
                                            <li>
                                                <form method="POST" action="{{ route('categories.destroy', $category) }}" class="m-0" onsubmit="return confirm('Delete this category?')">
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
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-stack fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No categories found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $categories->firstItem() }} to {{ $categories->lastItem() }} of {{ $categories->total() }} records
                </div>
                <div>
                    {{ $categories->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
