@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h1 class="h5 mb-0">Add User</h1>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.store') }}">
                        @include('users.form', ['user' => null])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush

