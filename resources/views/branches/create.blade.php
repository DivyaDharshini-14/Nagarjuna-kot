@extends('layouts.app')

@section('title', 'Add branch')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Add branch</h1>
        <form method="POST" action="{{ route('branches.store') }}">
            @include('branches.form', ['branch' => null])
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
