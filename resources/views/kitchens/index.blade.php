@extends('layouts.app')

@section('title', 'Kitchens')

@section('content')
    @include('partials.list-header', ['title' => 'Kitchens', 'permission' => 'kitchen.add', 'createRoute' => 'kitchens.create', 'createLabel' => 'Add kitchen'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Company</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($kitchens as $kitchen)
                    <tr>
                        <td>{{ $kitchen->name }}</td>
                        <td>{{ $kitchen->code }}</td>
                        <td>{{ $kitchen->company?->name }}</td>
                        <td>{{ $kitchen->branch?->name }}</td>
                        <td>@include('partials.status', ['active' => $kitchen->is_active])</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ $kitchen->branch && $kitchen->branch->code ? url('/' . $kitchen->branch->code . '/' . $kitchen->code) : route('kds.screen', $kitchen) }}" target="_blank" class="btn btn-sm btn-outline-dark me-1" title="Open Kitchen Display (TV)">
                                <i class="bi bi-tv"></i> KDS
                            </a>
                            @if(auth()->user()->hasPermission('kitchen.edit'))
                                <a href="{{ route('kitchens.edit', $kitchen) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @include('partials.delete-button', ['permission' => 'kitchen.delete', 'route' => 'kitchens.destroy', 'model' => $kitchen])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No kitchens found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $kitchens->links() }}</div>
@endsection
