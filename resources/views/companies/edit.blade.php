@extends('layouts.app')

@section('title', 'Edit company')

@section('content')
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-4">Edit company</h1>
            <form method="POST" action="{{ route('companies.update', $company) }}">
                @method('PUT')
                @include('companies.form')
            </form>
        </div>
    </div>
@endsection
