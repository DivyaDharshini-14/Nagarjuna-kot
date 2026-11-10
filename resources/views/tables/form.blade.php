@csrf
@include('partials.company-branch-fields')
<div class="mb-3">
    <label class="form-label" for="floor_id">Floor</label>
    <select class="form-select" id="floor_id" name="floor_id" data-floor-select data-url="{{ route('floors.options') }}" data-placeholder="Select floor" required>
        <option value="">Select floor</option>
        @foreach($floors as $floor)
            <option value="{{ $floor->id }}" @selected((string) old('floor_id', $table?->floor_id) === (string) $floor->id)>{{ $floor->name }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label" for="name">Table number / name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $table?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="seating_capacity">Seating capacity</label>
    <input class="form-control" id="seating_capacity" type="number" min="1" name="seating_capacity" value="{{ old('seating_capacity', $table?->seating_capacity) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="table_status">Table status</label>
    <select class="form-select" id="table_status" name="table_status" required>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(old('table_status', $table?->table_status ?? 'available') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $table?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
