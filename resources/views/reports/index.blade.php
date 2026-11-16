@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <h1 class="h3 mb-1">Reports</h1>
    <p class="text-muted mb-4">Review restaurant orders, kitchen preparation, prepared stock and staff activity.</p>
    <form method="GET" action="{{ route('reports.index') }}" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="report-type" class="form-label">Report</label>
                <select id="report-type" name="type" class="form-select">
                    @foreach($types as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="report-company" class="form-label">Company</label>
                <select id="report-company" name="company_id" class="form-select">
                    @if(auth()->user()->isAdmin())<option value="">All companies</option>@endif
                    @foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="report-branch" class="form-label">Branch</label>
                <select id="report-branch" name="branch_id" class="form-select">
                    @if(auth()->user()->isAdmin() || auth()->user()->hasRole('company-admin'))<option value="">All branches</option>@endif
                    @foreach($branches as $branch)<option value="{{ $branch->id }}" data-company="{{ $branch->company_id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->company?->name }} / {{ $branch->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="report-date-from" class="form-label">From date</label>
                <input id="report-date-from" class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] }}" required>
            </div>
            <div class="col-md-3">
                <label for="report-date-to" class="form-label">To date</label>
                <input id="report-date-to" class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] }}" required>
            </div>
            <div class="col-md-3" data-order-filter>
                <label for="report-kitchen" class="form-label">Kitchen</label>
                <select id="report-kitchen" class="form-select" name="kitchen_id">
                    <option value="">All permitted kitchens</option>
                    @foreach($kitchens as $kitchen)<option value="{{ $kitchen->id }}" data-company="{{ $kitchen->company_id }}" data-branch="{{ $kitchen->branch_id }}" @selected((string) ($filters['kitchen_id'] ?? '') === (string) $kitchen->id)>{{ $kitchen->branch?->name }} / {{ $kitchen->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3" data-order-filter>
                <label for="report-order" class="form-label">Order number</label>
                <input id="report-order" class="form-control" name="order_number" value="{{ $filters['order_number'] ?? '' }}" maxlength="100">
            </div>
            <div class="col-md-4" data-order-filter>
                <label for="report-status" class="form-label">Status</label>
                <input id="report-status" class="form-control" name="status" list="report-statuses" value="{{ $filters['status'] ?? '' }}" maxlength="50" placeholder="All statuses">
                <datalist id="report-statuses">@foreach(['new','accepted','preparing','ready','served','completed','cancelled','confirmed','pending','approved','rejected'] as $status)<option value="{{ $status }}">@endforeach</datalist>
                <div class="form-text">Order, KOT, dish or cancellation status for the selected report. Stock reports use linked order status.</div>
            </div>
            <div class="col-md-4" id="activity-filter">
                <label for="report-action" class="form-label">Activity action</label>
                <input id="report-action" class="form-control" name="action" value="{{ $filters['action'] ?? '' }}" maxlength="100" placeholder="All actions">
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a href="{{ route('reports.index', ['type' => $type]) }}" class="btn btn-outline-secondary">Reset</a>
                @if(auth()->user()->hasPermission('report.export') && ($type !== 'user-activity' || auth()->user()->hasPermission('audit-log.export')))
                    <button class="btn btn-outline-success ms-auto" type="submit" name="export" value="1">Export filtered CSV</button>
                @endif
            </div>
        </div>
    </form>
    <h2 class="h5">{{ $types[$type] }}</h2>
    <p class="text-muted small">{{ $description }}</p>
    <p class="small text-muted">{{ number_format($records->total()) }} records match these filters.</p>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead><tr>@foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>@foreach($columns as $key => $label)<td>{{ $record->{$key} ?? '—' }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-5">No records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
    <script>
        (() => {
            const type = document.getElementById('report-type');
            const company = document.getElementById('report-company');
            const branch = document.getElementById('report-branch');
            const kitchen = document.getElementById('report-kitchen');
            function updateFilters() {
                const activity = type.value === 'user-activity';
                document.querySelectorAll('[data-order-filter]').forEach(group => {
                    group.hidden = activity;
                    group.querySelectorAll('input,select').forEach(input => input.disabled = activity);
                });
                document.getElementById('activity-filter').hidden = !activity;
                document.getElementById('report-action').disabled = !activity;
                for (const option of branch.options) {
                    const allowed = !option.value || !company.value || option.dataset.company === company.value;
                    option.hidden = !allowed; option.disabled = !allowed;
                }
                if (branch.selectedOptions[0]?.disabled) branch.value = '';
                for (const option of kitchen.options) {
                    const allowed = !option.value || ((!company.value || option.dataset.company === company.value) && (!branch.value || option.dataset.branch === branch.value));
                    option.hidden = !allowed; option.disabled = !allowed;
                }
                if (kitchen.selectedOptions[0]?.disabled) kitchen.value = '';
            }
            [type, company, branch].forEach(input => input.addEventListener('change', updateFilters));
            updateFilters();
        })();
    </script>
@endsection
