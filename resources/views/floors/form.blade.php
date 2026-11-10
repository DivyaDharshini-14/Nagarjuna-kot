@csrf
@include('partials.company-branch-fields')
<div class="mb-3">
    <label class="form-label" for="name">Floor name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $floor?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="code">Floor code</label>
    <input class="form-control" id="code" name="code" value="{{ old('code', $floor?->code) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $floor?->description) }}</textarea>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $floor?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('floors.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
