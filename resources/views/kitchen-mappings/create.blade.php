@extends('layouts.app')

@section('title', 'Add kitchen mapping')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add kitchen mapping</h1>
        <form method="POST" action="{{ route('kitchen-mappings.store') }}">
            @include('kitchen-mappings.form', ['mapping' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
