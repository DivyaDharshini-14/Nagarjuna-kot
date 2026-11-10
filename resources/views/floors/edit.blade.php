@extends('layouts.app')

@section('title', 'Edit floor')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit floor</h1>
        <form method="POST" action="{{ route('floors.update', $floor) }}">
            @method('PUT')
            @include('floors.form')
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
