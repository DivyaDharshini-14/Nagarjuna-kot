@extends('layouts.app')

@section('title', 'Edit category')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit category</h1>
        <form method="POST" action="{{ route('categories.update', $category) }}">
            @method('PUT')
            @include('categories.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
