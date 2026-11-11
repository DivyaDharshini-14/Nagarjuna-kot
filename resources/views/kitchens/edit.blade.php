@extends('layouts.app')

@section('title', 'Edit kitchen')

@section('content')
    <div class="card"><div class="card-body">
        <h1 class="h4 mb-4">Edit kitchen</h1>
        <form method="POST" action="{{ route('kitchens.update', $kitchen) }}">
            @method('PUT')
            @include('kitchens.form')
        </form>
    </div></div>

    <div class="card mt-3"><div class="card-body">
        <h2 class="h5">Kitchen TV display</h2>
        <p class="text-muted small">Register this kitchen's display to create a revocable, kitchen-scoped URL. Anyone with the URL can view and update this kitchen's KOTs, so keep it on the authorized TV.</p>
        @if($kitchen->display_token_hash)
            <div class="alert alert-success py-2">A display URL is registered. The URL is shown only once when it is generated.</div>
            <form method="POST" action="{{ route('kitchens.display-revoke', $kitchen) }}" onsubmit="return confirm('Revoke the current TV display URL? The screen will stop loading after revocation.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit">Revoke display URL</button>
            </form>
        @else
            <div class="text-muted small mb-2">No dedicated TV display URL is registered.</div>
        @endif
        @if(session('registered_display_url'))
            <div class="alert alert-warning mt-3 mb-0">
                <strong>Copy this URL to the kitchen TV now. It will not be shown again.</strong>
                <input class="form-control mt-2" readonly value="{{ session('registered_display_url') }}" onclick="this.select()">
            </div>
        @endif
        <form method="POST" action="{{ route('kitchens.display-register', $kitchen) }}" class="mt-3">
            @csrf
            <button class="btn btn-primary" type="submit">{{ $kitchen->display_token_hash ? 'Generate replacement URL' : 'Register TV display' }}</button>
        </form>
    </div></div>
@endsection

@push('scripts')
    @include('partials.dependent-selects')
@endpush
