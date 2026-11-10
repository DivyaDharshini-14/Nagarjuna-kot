@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <h1 class="h4">{{ auth()->user()->name }}</h1>
            <p class="text-muted mb-0">
                {{ auth()->user()->branch?->name ?? auth()->user()->company?->name ?? 'System administration' }}
            </p>
        </div>
    </div>
@endsection
