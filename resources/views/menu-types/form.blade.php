@csrf

<div class="mb-3">
    <label class="form-label" for="name">Type name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $menuType?->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $menuType?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $menuType?->is_active ?? true])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('menu-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
