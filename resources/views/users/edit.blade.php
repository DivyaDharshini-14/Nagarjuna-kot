@extends('layouts.app')

@section('title', 'Edit User: '.$user->name)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h1 class="h5 mb-0">Edit User: {{ $user->name }}</h1>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @method('PUT')
                        @include('users.form', ['user' => $user])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush

