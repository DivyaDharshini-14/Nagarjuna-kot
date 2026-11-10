@extends('layouts.app')

@section('title', 'Edit Role: '.$role->name)

@section('content')
    <div class="card">
        <div class="card-header bg-white">
            <h1 class="h5 mb-0">Edit Role & Permissions: {{ $role->name }}</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.update', $role) }}">
                @method('PUT')
                @include('roles.form', ['role' => $role])
            </form>
        </div>
    </div>
@endsection

