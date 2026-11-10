@csrf
@include('partials.company-branch-fields', ['branchRequired' => false])
<div class="mb-3">
    <label class="form-label" for="name">Category name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $category?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="code">Category code</label>
    <input class="form-control" id="code" name="code" value="{{ old('code', $category?->code) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $category?->description) }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label" for="display_order">Display order</label>
    <input class="form-control" id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', $category?->display_order ?? 0) }}" required>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $category?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
