@extends('layouts.app')

@section('title', 'Add Role')

@section('content')
    <div class="card">
        <div class="card-header bg-white">
            <h1 class="h5 mb-0">Add Role & Permissions</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.store') }}">
                @include('roles.form', ['role' => null])
            </form>
        </div>
    </div>
@endsection

