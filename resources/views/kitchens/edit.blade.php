@extends('layouts.app')

@section('title', 'Edit kitchen')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit kitchen</h1>
        <form method="POST" action="{{ route('kitchens.update', $kitchen) }}">
            @method('PUT')
            @include('kitchens.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
