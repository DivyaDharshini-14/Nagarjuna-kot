<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-0">{{ $title }}</h1>
        <span class="text-muted small">Manage records, configuration and statuses</span>
    </div>
    @if(($canCreate ?? true) && auth()->user()->hasPermission($permission))
        <a href="{{ route($createRoute) }}" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>{{ $createLabel }}</span>
        </a>
    @endif
</div>
