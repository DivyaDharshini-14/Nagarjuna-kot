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
    <label class="form-label" for="menu_item_id">Menu item</label>
    <select
        class="form-select @error('menu_item_id') is-invalid @enderror"
        id="menu_item_id"
        name="menu_item_id"
        data-menu-item-select
        data-url="{{ route('menu-items.options') }}"
        data-placeholder="Select menu item"
        required
    >
        <option value="">Select menu item</option>
        @foreach($menuItems as $menuItem)
            <option value="{{ $menuItem->id }}" @selected((string) old('menu_item_id', $mapping?->menu_item_id) === (string) $menuItem->id)>
                {{ $menuItem->name }} ({{ $menuItem->code }})
            </option>
        @endforeach
    </select>
    @error('menu_item_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold" for="kitchen_ids">Kitchen Stations</label>
    
    {{-- Underlying select kept in sync for form submission and dynamic branch reload --}}
    <select
        class="d-none"
        id="kitchen_ids"
        name="kitchen_ids[]"
        data-kitchen-select
        data-url="{{ route('kitchens.options') }}"
        data-placeholder="Select kitchen stations"
        multiple
    >
        @foreach($kitchens as $kitchen)
            <option value="{{ $kitchen->id }}" @selected(in_array((string) $kitchen->id, array_map('strval', (array) old('kitchen_ids', $selectedKitchenIds ?? [])), true))>
                {{ $kitchen->name }} ({{ $kitchen->code }})
            </option>
        @endforeach
    </select>

    {{-- Custom Tag/Pill Multi-select Dropdown (matching reference UI) --}}
    <div class="custom-multiselect-container" id="kitchen-multiselect-container">
        <div class="custom-multiselect-trigger @error('kitchen_ids') is-invalid @enderror @error('kitchen_ids.*') is-invalid @enderror" id="kitchen-multiselect-trigger" tabindex="0">
            <div class="custom-multiselect-tags" id="kitchen-multiselect-tags">
                <span class="custom-multiselect-placeholder">Select kitchen stations...</span>
            </div>
            <span class="custom-multiselect-chevron">
                <i class="bi bi-chevron-down"></i>
            </span>
        </div>

        <div class="custom-multiselect-dropdown" id="kitchen-multiselect-dropdown">
            {{-- Dynamically populated from raw select options --}}
        </div>
    </div>

    @error('kitchen_ids')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('kitchen_ids.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<style>
.custom-multiselect-container {
    position: relative;
    user-select: none;
}
.custom-multiselect-trigger {
    min-height: 48px;
    padding: 6px 12px;
    background-color: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.custom-multiselect-trigger:hover {
    border-color: #adb5bd;
}
.custom-multiselect-trigger:focus,
.custom-multiselect-trigger.is-open {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    outline: none;
}
.custom-multiselect-trigger.is-invalid {
    border-color: #dc3545;
}
.custom-multiselect-trigger.is-invalid.is-open,
.custom-multiselect-trigger.is-invalid:focus {
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
}
.custom-multiselect-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    flex: 1;
}
.custom-multiselect-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: #e2e8f0;
    color: #1e293b;
    font-size: 0.85rem;
    font-weight: 500;
    padding: 3px 6px 3px 12px;
    border-radius: 9999px;
    line-height: 1.25;
}
.custom-multiselect-tag-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background-color: #64748b;
    color: #ffffff;
    border: none;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.custom-multiselect-tag-remove:hover {
    background-color: #334155;
}
.custom-multiselect-placeholder {
    color: #6c757d;
    font-size: 0.9rem;
}
.custom-multiselect-chevron {
    color: #64748b;
    display: inline-flex;
    align-items: center;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.custom-multiselect-trigger.is-open .custom-multiselect-chevron {
    transform: rotate(180deg);
}
.custom-multiselect-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
    padding: 6px 0;
    max-height: 240px;
    overflow-y: auto;
    display: none;
}
.custom-multiselect-dropdown.show {
    display: block;
}
.custom-multiselect-item {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    font-size: 0.9rem;
    color: #1e293b;
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.custom-multiselect-item:hover {
    background-color: #f1f5f9;
}
.custom-multiselect-item.selected {
    font-weight: 500;
}
.custom-multiselect-item-check {
    width: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    flex-shrink: 0;
}
.custom-multiselect-empty {
    padding: 12px 16px;
    text-align: center;
    color: #64748b;
    font-size: 0.85rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rawSelect = document.getElementById('kitchen_ids');
    const container = document.getElementById('kitchen-multiselect-container');
    const trigger = document.getElementById('kitchen-multiselect-trigger');
    const tagsWrapper = document.getElementById('kitchen-multiselect-tags');
    const dropdown = document.getElementById('kitchen-multiselect-dropdown');

    if (!rawSelect || !container) return;

    function render() {
        tagsWrapper.innerHTML = '';
        dropdown.innerHTML = '';

        const options = Array.from(rawSelect.options).filter(opt => opt.value !== '');

        if (options.length === 0) {
            dropdown.innerHTML = '<div class="custom-multiselect-empty">No kitchen stations available</div>';
            tagsWrapper.innerHTML = '<span class="custom-multiselect-placeholder">Select kitchen stations...</span>';
            return;
        }

        let hasSelected = false;

        options.forEach(opt => {
            const isSelected = opt.selected;

            // Render pill in trigger box if selected
            if (isSelected) {
                hasSelected = true;
                const tag = document.createElement('span');
                tag.className = 'custom-multiselect-tag';

                const labelSpan = document.createElement('span');
                labelSpan.textContent = opt.textContent.trim();
                tag.appendChild(labelSpan);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'custom-multiselect-tag-remove';
                removeBtn.dataset.value = opt.value;
                removeBtn.title = 'Remove';
                removeBtn.innerHTML = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                tag.appendChild(removeBtn);

                tagsWrapper.appendChild(tag);
            }

            // Render option row in dropdown
            const item = document.createElement('div');
            item.className = 'custom-multiselect-item' + (isSelected ? ' selected' : '');
            item.dataset.value = opt.value;

            const checkSpan = document.createElement('span');
            checkSpan.className = 'custom-multiselect-item-check';
            if (isSelected) {
                checkSpan.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            }
            item.appendChild(checkSpan);

            const textSpan = document.createElement('span');
            textSpan.textContent = opt.textContent.trim();
            item.appendChild(textSpan);

            item.addEventListener('click', function (e) {
                e.stopPropagation();
                opt.selected = !opt.selected;
                trigger.classList.remove('is-invalid');
                rawSelect.dispatchEvent(new Event('change', { bubbles: true }));
                render();
            });

            dropdown.appendChild(item);
        });

        if (!hasSelected) {
            tagsWrapper.innerHTML = '<span class="custom-multiselect-placeholder">Select kitchen stations...</span>';
        }

        // Attach tag remove button click handlers
        tagsWrapper.querySelectorAll('.custom-multiselect-tag-remove').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const val = btn.dataset.value;
                const targetOpt = Array.from(rawSelect.options).find(o => o.value === val);
                if (targetOpt) {
                    targetOpt.selected = false;
                    rawSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    render();
                }
            });
        });
    }

    // Toggle dropdown open/close
    trigger.addEventListener('click', function () {
        const isOpen = dropdown.classList.contains('show');
        if (isOpen) {
            dropdown.classList.remove('show');
            trigger.classList.remove('is-open');
        } else {
            dropdown.classList.add('show');
            trigger.classList.add('is-open');
        }
    });

    // Keyboard support for trigger (Enter or Space to open/close)
    trigger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            trigger.click();
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!container.contains(e.target)) {
            dropdown.classList.remove('show');
            trigger.classList.remove('is-open');
        }
    });

    // Close on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            trigger.classList.remove('is-open');
        }
    });

    // Intercept enclosing form submission to validate client-side
    const form = rawSelect.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const hasSelected = Array.from(rawSelect.options).some(opt => opt.selected && opt.value !== '');
            if (!hasSelected) {
                e.preventDefault();
                trigger.classList.add('is-invalid');
                dropdown.classList.add('show');
                trigger.classList.add('is-open');
                trigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // Listen to changes on rawSelect (dynamic reload from branch changes)
    rawSelect.addEventListener('options-reloaded', render);
    rawSelect.addEventListener('change', render);

    // MutationObserver as fallback for any dynamic DOM option changes
    const observer = new MutationObserver(function () {
        render();
    });
    observer.observe(rawSelect, { childList: true });

    // Initial render
    render();
});
</script>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => old('is_active', $mapping?->is_active ?? true)])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save Assignments</button>
    <a href="{{ route('kitchen-mappings.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
