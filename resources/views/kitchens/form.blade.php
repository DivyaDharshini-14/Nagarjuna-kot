@csrf
@include('partials.company-branch-fields')
<div class="mb-3">
    <label class="form-label" for="name">Kitchen name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $kitchen?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="code">Kitchen code</label>
    <input class="form-control" id="code" name="code" value="{{ old('code', $kitchen?->code) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="kitchen_type">Kitchen type <span class="text-muted small">(optional)</span></label>
    <input class="form-control" id="kitchen_type" name="kitchen_type" value="{{ old('kitchen_type', $kitchen?->kitchen_type) }}" placeholder="e.g. Main Kitchen, Biryani Kitchen, Bakery, Starters">
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $kitchen?->description) }}</textarea>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $kitchen?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('kitchens.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
