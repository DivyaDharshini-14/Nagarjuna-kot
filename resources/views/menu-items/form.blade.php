@csrf
@include('partials.company-branch-fields')
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="category_id">Category</label>
        <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id" data-category-select data-url="{{ route('categories.options') }}" data-placeholder="Select category" required>
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $menuItem?->category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="menu_type_id">Menu type</label>
        <select class="form-select @error('menu_type_id') is-invalid @enderror" id="menu_type_id" name="menu_type_id" data-menu-type-select data-url="{{ route('menu-types.options') }}" data-placeholder="Select menu type" required>
            <option value="">Select menu type</option>
            @foreach($menuTypes as $menuType)
                <option value="{{ $menuType->id }}" @selected((string) old('menu_type_id', $menuItem?->menu_type_id) === (string) $menuType->id)>
                    {{ $menuType->name }}@if(!$menuType->is_active) (Inactive)@endif
                </option>
            @endforeach
        </select>
        @error('menu_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="row">
    <div class="col-md-8 mb-3">
        <label class="form-label" for="name">Dish name</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $menuItem?->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" for="code">Dish code</label>
        <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $menuItem?->code) }}" required>
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label" for="selling_price">Selling price</label>
        <input class="form-control @error('selling_price') is-invalid @enderror" id="selling_price" type="number" min="0" step="0.01" name="selling_price" value="{{ old('selling_price', $menuItem?->selling_price) }}" required>
        @error('selling_price')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" for="display_order">Display order</label>
        <input class="form-control @error('display_order') is-invalid @enderror" id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', $menuItem?->display_order ?? 0) }}" required>
        @error('display_order')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" for="availability">Availability</label>
        <select class="form-select @error('availability') is-invalid @enderror" id="availability" name="availability" required>
            @foreach($availabilities as $value => $label)
                <option value="{{ $value }}" @selected(old('availability', $menuItem?->availability ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('availability')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="mb-3">
    <label class="form-label" for="image">Dish image</label>
    <input class="form-control @error('image') is-invalid @enderror" id="image" type="file" name="image" accept="image/png,image/jpeg,image/webp">
    @if($menuItem?->image)
        <img src="{{ asset('storage/'.$menuItem->image) }}" alt="" class="img-thumbnail mt-2" style="max-height: 120px">
    @endif
    @error('image')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $menuItem?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="preparation_instructions">Preparation instructions</label>
    <textarea class="form-control @error('preparation_instructions') is-invalid @enderror" id="preparation_instructions" name="preparation_instructions" rows="3">{{ old('preparation_instructions', $menuItem?->preparation_instructions) }}</textarea>
    @error('preparation_instructions')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
@include('partials.checkbox', ['name' => 'stock_tracking_enabled', 'label' => 'Track prepared dish stock', 'checked' => $menuItem?->stock_tracking_enabled ?? false])
<div class="mb-3">
    <label class="form-label" for="prepared_stock_quantity">Prepared quantity available</label>
    <input
        class="form-control @error('prepared_stock_quantity') is-invalid @enderror"
        id="prepared_stock_quantity"
        type="number"
        min="0"
        max="999999"
        step="1"
        name="prepared_stock_quantity"
        value="{{ old('prepared_stock_quantity', $menuItem?->prepared_stock_quantity) }}"
        aria-describedby="prepared-stock-help"
    >
    <div class="form-text" id="prepared-stock-help">Required when stock tracking is enabled. Set this to the number of portions currently available.</div>
    @error('prepared_stock_quantity')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $menuItem?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('menu-items.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
