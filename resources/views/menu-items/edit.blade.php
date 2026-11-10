@extends('layouts.app')

@section('title', 'Edit menu item')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit menu item</h1>
        <form method="POST" action="{{ route('menu-items.update', $menuItem) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('menu-items.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
