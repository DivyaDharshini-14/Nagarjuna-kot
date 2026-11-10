@extends('layouts.app')

@section('title', 'Add floor')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add floor</h1>
        <form method="POST" action="{{ route('floors.store') }}">
            @include('floors.form', ['floor' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
