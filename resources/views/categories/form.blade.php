@csrf

<div class="mb-3">
    <label class="form-label" for="name">Category name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category?->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="code">Category code</label>
    <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $category?->code) }}" required>
    @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $category?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="display_order">Display order</label>
    <input class="form-control @error('display_order') is-invalid @enderror" id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', $category?->display_order ?? 0) }}" required>
    @error('display_order')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $category?->is_active ?? true])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
