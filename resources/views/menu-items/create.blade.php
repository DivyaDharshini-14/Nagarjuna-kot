@extends('layouts.app')

@section('title', 'Add menu item')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add menu item</h1>
        <form method="POST" action="{{ route('menu-items.store') }}" enctype="multipart/form-data">
            @include('menu-items.form', ['menuItem' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
