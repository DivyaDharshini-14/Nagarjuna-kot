@csrf
@include('partials.company-branch-fields', ['branchRequired' => false])
<div class="mb-3">
    <label class="form-label" for="name">Type name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $menuType?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $menuType?->description) }}</textarea>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $menuType?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('menu-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
