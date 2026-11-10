@extends('layouts.app')

@section('title', 'Add table')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add table</h1>
        <form method="POST" action="{{ route('tables.store') }}">
            @include('tables.form', ['table' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
