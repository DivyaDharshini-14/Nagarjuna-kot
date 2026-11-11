@extends('layouts.app')

@section('title', 'Kitchen Display System (KDS)')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-tv text-primary me-2"></i>Kitchen Display Screens</h1>
    <p class="text-muted small mb-0">Select a kitchen station to launch its dedicated live TV display screen, or view all kitchens combined.</p>
</div>

{{-- Master All-Kitchens Card --}}
<div class="card bg-dark text-white border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-warning text-dark fw-bold px-3 py-1">CENTRAL DISPLAY</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Live Monitoring
                </span>
            </div>
            <h4 class="fw-bold mb-1">Master Kitchen Display (All Kitchens Combined)</h4>
            <p class="text-white-50 small mb-0">Monitors live tickets from all kitchens simultaneously on a single unified screen.</p>
        </div>
        <div>
            <a href="{{ route('kds.screen.all') }}" target="_blank" class="btn btn-warning text-dark fw-bold px-4 py-2 shadow-sm">
                <i class="bi bi-box-arrow-up-right me-2"></i>Launch Master TV Screen
            </a>
        </div>
    </div>
</div>

<h5 class="fw-bold text-dark mb-3"><i class="bi bi-layers me-2 text-primary"></i>Individual Kitchen Station Screens</h5>

@php
    $groupedKitchens = $kitchens->groupBy(fn($k) => $k->branch?->name ?? 'Main Branch');
@endphp

@forelse($groupedKitchens as $branchName => $branchKitchens)
    <div class="d-flex align-items-center gap-2 mb-3 mt-4">
        <span class="badge bg-dark px-3 py-2 fs-6"><i class="bi bi-geo-alt-fill me-1 text-warning"></i>{{ $branchName }}</span>
        <span class="text-muted small">({{ $branchKitchens->count() }} active {{ Str::plural('station', $branchKitchens->count()) }})</span>
    </div>

    <div class="row g-4 mb-4">
        @foreach($branchKitchens as $k)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm border-0 border-top border-4 border-primary rounded-3">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-dark fs-6 px-3 py-2 font-monospace">{{ $k->code }}</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Active Station
                                </span>
                            </div>
                            <h4 class="card-title fw-bold text-dark mb-1">{{ $k->name }} Kitchen</h4>
                            <div class="text-muted small mb-3">
                                <i class="bi bi-geo-alt me-1 text-primary"></i><strong>{{ $k->branch?->name ?? 'Main Branch' }}</strong>
                                @if($k->description)
                                    <span class="d-block mt-1 text-secondary">{{ $k->description }}</span>
                                @endif
                            </div>

                            {{-- Order count indicators --}}
                            <div class="row g-2 text-center my-3">
                                <div class="col-4">
                                    <div class="p-2 rounded bg-danger-subtle border border-danger-subtle">
                                        <div class="fs-4 fw-bold text-danger">{{ $k->new_count ?? 0 }}</div>
                                        <div class="text-muted text-uppercase" style="font-size: 0.68rem; font-weight: 700;">New</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded bg-warning-subtle border border-warning-subtle">
                                        <div class="fs-4 fw-bold text-warning-emphasis">{{ $k->preparing_count ?? 0 }}</div>
                                        <div class="text-muted text-uppercase" style="font-size: 0.68rem; font-weight: 700;">Preparing</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded bg-success-subtle border border-success-subtle">
                                        <div class="fs-4 fw-bold text-success">{{ $k->ready_count ?? 0 }}</div>
                                        <div class="text-muted text-uppercase" style="font-size: 0.68rem; font-weight: 700;">Ready</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('kds.screen', $k) }}" target="_blank" class="btn btn-primary fw-bold py-2 shadow-sm">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Launch {{ $k->name }} ({{ $k->code }}) TV Screen
                            </a>
                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.72rem;">
                                <span>ID: <code>/kds/{{ $k->id }}</code></span>
                                @if($k->branch)
                                    <span>Code: <code>/kds/{{ $k->branch->code }}/{{ $k->code }}</code></span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@empty
    <div class="col-12 text-center py-5">
        <div class="p-5 bg-white rounded-3 shadow-sm border">
            <i class="bi bi-tv fs-1 text-muted d-block mb-3"></i>
            <h5>No Active Kitchens Configured</h5>
            <p class="text-muted">Configure kitchens under Restaurant Masters > Kitchens to open Kitchen Display screens.</p>
            @if(auth()->user()->hasPermission('kitchen.add'))
                <a href="{{ route('kitchens.create') }}" class="btn btn-primary mt-2">Add New Kitchen</a>
            @endif
        </div>
    </div>
@endforelse
@endsection
