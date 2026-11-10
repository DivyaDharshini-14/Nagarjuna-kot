@extends('layouts.app')

@section('title', 'Tables')

@section('content')
    @include('partials.list-header', ['title' => 'Tables', 'permission' => 'table.add', 'createRoute' => 'tables.create', 'createLabel' => 'Add table'])
    <div class="card"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Branch</th><th>Floor</th><th>Seats</th><th>Table status</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($tables as $table)
                    <tr>
                        <td>{{ $table->name }}</td>
                        <td>{{ $table->branch?->name }}</td>
                        <td>{{ $table->floor?->name }}</td>
                        <td>{{ $table->seating_capacity }}</td>
                        <td>{{ ucfirst($table->table_status) }}</td>
                        <td>@include('partials.status', ['active' => $table->is_active])</td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->hasPermission('table.edit'))
                                <a href="{{ route('tables.edit', $table) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            @include('partials.delete-button', ['permission' => 'table.delete', 'route' => 'tables.destroy', 'model' => $table])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No tables found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $tables->links() }}</div>
@endsection
