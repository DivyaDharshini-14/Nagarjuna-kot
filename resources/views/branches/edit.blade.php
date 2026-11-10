@extends('layouts.app')

@section('title', 'Edit branch')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit branch</h1>
        <form method="POST" action="{{ route('branches.update', $branch) }}">
            @method('PUT')
            @include('branches.form')
        </form>
    </div></div>
@endsection
