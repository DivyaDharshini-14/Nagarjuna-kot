@extends('layouts.app')

@section('title', 'Edit kitchen mapping')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit kitchen mapping</h1>
        <form method="POST" action="{{ route('kitchen-mappings.update', $mapping) }}">
            @method('PUT')
            @include('kitchen-mappings.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
