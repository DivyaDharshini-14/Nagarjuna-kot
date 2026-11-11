@csrf

<div class="mb-3">
    <label class="form-label" for="branch_id">Branch</label>
    @if($lockBranch)
        <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
    @endif
    <select
        id="branch_id"
        class="form-select @error('branch_id') is-invalid @enderror"
        data-branch-select
        data-url="{{ route('branches.options') }}"
        data-placeholder="Select branch"
        @unless($lockBranch) name="branch_id" required @else disabled @endunless
    >
        <option value="">Select branch</option>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranch) === (string) $branch->id)>
                {{ $branch->name }}@if(!empty($isSuperAdmin) && $branch->company) ({{ $branch->company->name }})@endif
            </option>
        @endforeach
    </select>
    @error('branch_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="floor_id">Floor</label>
    <select class="form-select @error('floor_id') is-invalid @enderror" id="floor_id" name="floor_id" data-floor-select data-url="{{ route('floors.options') }}" data-placeholder="Select floor" required>
        <option value="">Select floor</option>
        @foreach($floors as $floor)
            <option value="{{ $floor->id }}" @selected((string) old('floor_id', $table?->floor_id) === (string) $floor->id)>
                {{ $floor->name }}@if(!$floor->is_active) (Inactive)@endif
            </option>
        @endforeach
    </select>
    @error('floor_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="name">Table number / name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $table?->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="seating_capacity">Seating capacity</label>
    <input class="form-control @error('seating_capacity') is-invalid @enderror" id="seating_capacity" type="number" min="1" name="seating_capacity" value="{{ old('seating_capacity', $table?->seating_capacity) }}" required>
    @error('seating_capacity')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="table_status">Table status</label>
    <select class="form-select @error('table_status') is-invalid @enderror" id="table_status" name="table_status" required>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(old('table_status', $table?->table_status ?? 'available') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('table_status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $table?->is_active ?? true])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
