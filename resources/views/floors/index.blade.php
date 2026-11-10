@extends('layouts.app')

@section('title', 'Floors')

@section('content')
    @include('partials.list-header', ['title' => 'Floors', 'permission' => 'floor.add', 'createRoute' => 'floors.create', 'createLabel' => 'Add floor'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Company</th><th>Branch</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($floors as $floor)
                    <tr>
                        <td>{{ $floor->name }}</td>
                        <td>{{ $floor->code }}</td>
                        <td>{{ $floor->company?->name }}</td>
                        <td>{{ $floor->branch?->name }}</td>
                        <td>@include('partials.status', ['active' => $floor->is_active])</td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->hasPermission('floor.edit'))
                                <a href="{{ route('floors.edit', $floor) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @include('partials.delete-button', ['permission' => 'floor.delete', 'route' => 'floors.destroy', 'model' => $floor])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No floors found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $floors->links() }}</div>
@endsection
