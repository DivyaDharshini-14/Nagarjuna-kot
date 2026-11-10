@extends('layouts.app')

@section('title', 'Edit menu type')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit menu type</h1>
        <form method="POST" action="{{ route('menu-types.update', $menuType) }}">
            @method('PUT')
            @include('menu-types.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
