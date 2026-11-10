@extends('layouts.app')

@section('title', 'Edit table')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit table</h1>
        <form method="POST" action="{{ route('tables.update', $table) }}">
            @method('PUT')
            @include('tables.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
