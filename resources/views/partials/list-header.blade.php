<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold text-dark mb-0">{{ $title }}</h1>
        <span class="text-muted small">Manage records, configuration and statuses</span>
    </div>
    @if(($canCreate ?? true) && auth()->user()->hasPermission($permission))
        <a href="{{ route($createRoute) }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ $createLabel }}
        </a>
    @endif
</div>

<div class="card shadow-sm border-0 mb-4 p-3 bg-white" style="border-radius: 14px;">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted border-end-0">
                    <i class="bi bi-search"></i>
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control form-control-sm border-start-0 ps-0"
                    placeholder="Search by name, code or keyword..."
                >
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary">
                <i class="bi bi-filter me-1"></i>Filter
            </button>
            <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </form>
</div>
