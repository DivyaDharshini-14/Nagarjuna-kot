@extends('layouts.app')

@section('title', 'Add category')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add category</h1>
        <form method="POST" action="{{ route('categories.store') }}">
            @include('categories.form', ['category' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
