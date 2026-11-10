@extends('layouts.app')

@section('title', 'Add company')

@section('content')
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-4">Add company</h1>
            <form method="POST" action="{{ route('companies.store') }}">
                @include('companies.form', ['company' => null])
            </form>
        </div>
    </div>
@endsection
