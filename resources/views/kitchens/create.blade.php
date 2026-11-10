@extends('layouts.app')

@section('title', 'Add kitchen')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add kitchen</h1>
        <form method="POST" action="{{ route('kitchens.store') }}">
            @include('kitchens.form', ['kitchen' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
