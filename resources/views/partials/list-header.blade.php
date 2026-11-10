<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">{{ $title }}</h1>
    @if(($canCreate ?? true) && auth()->user()->hasPermission($permission))
        <a href="{{ route($createRoute) }}" class="btn btn-primary">{{ $createLabel }}</a>
    @endif
</div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="col-md-auto">
        <button class="btn btn-outline-primary">Filter</button>
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
