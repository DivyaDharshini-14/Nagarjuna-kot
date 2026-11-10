@extends('layouts.app')

@section('title', 'Add menu type')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add menu type</h1>
        <form method="POST" action="{{ route('menu-types.store') }}">
            @include('menu-types.form', ['menuType' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
