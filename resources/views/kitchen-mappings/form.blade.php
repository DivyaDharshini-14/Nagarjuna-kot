@csrf
@include('partials.company-branch-fields')
<div class="mb-3">
    <label class="form-label" for="menu_item_id">Menu item</label>
    <select class="form-select" id="menu_item_id" name="menu_item_id" data-menu-item-select data-url="{{ route('menu-items.options') }}" data-placeholder="Select menu item" required>
        <option value="">Select menu item</option>
        @foreach($menuItems as $menuItem)
            <option value="{{ $menuItem->id }}" @selected((string) old('menu_item_id', $mapping?->menu_item_id) === (string) $menuItem->id)>{{ $menuItem->name }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label" for="kitchen_id">Kitchen</label>
    <select class="form-select" id="kitchen_id" name="kitchen_id" data-kitchen-select data-url="{{ route('kitchens.options') }}" data-placeholder="Select kitchen" required>
        <option value="">Select kitchen</option>
        @foreach($kitchens as $kitchen)
            <option value="{{ $kitchen->id }}" @selected((string) old('kitchen_id', $mapping?->kitchen_id) === (string) $kitchen->id)>{{ $kitchen->name }}</option>
        @endforeach
    </select>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $mapping?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('kitchen-mappings.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
