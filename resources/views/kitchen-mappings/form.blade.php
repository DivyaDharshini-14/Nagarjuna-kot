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
    <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label fw-semibold mb-0" for="menu_item_ids">Menu Items</label>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary" id="menu-item-select-all" style="font-size: 0.8rem;">Select all</button>
            <span class="text-muted" style="font-size: 0.8rem;">·</span>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-secondary" id="menu-item-clear-all" style="font-size: 0.8rem;">Clear</button>
        </div>
    </div>
    
    {{-- Underlying select kept in sync for form submission and dynamic branch reload --}}
    <select
        class="d-none"
        id="menu_item_ids"
        name="menu_item_ids[]"
        data-menu-item-select
        data-url="{{ route('menu-items.options') }}"
        data-placeholder="Select menu items"
        multiple
    >
        @foreach($menuItems as $menuItem)
            <option value="{{ $menuItem->id }}" @selected(in_array((string) $menuItem->id, array_map('strval', (array) old('menu_item_ids', $selectedMenuItemIds ?? [])), true))>
                {{ $menuItem->name }} ({{ $menuItem->code }})
            </option>
        @endforeach
    </select>

    {{-- Custom Tag/Pill Multi-select Dropdown --}}
    <div class="custom-multiselect-container" id="menu-item-multiselect-container">
        <div class="custom-multiselect-trigger @error('menu_item_ids') is-invalid @enderror @error('menu_item_ids.*') is-invalid @enderror" id="menu-item-multiselect-trigger" tabindex="0">
            <div class="custom-multiselect-tags" id="menu-item-multiselect-tags">
                <span class="custom-multiselect-placeholder">Select menu items...</span>
            </div>
            <span class="custom-multiselect-chevron">
                <i class="bi bi-chevron-down"></i>
            </span>
        </div>

        <div class="custom-multiselect-dropdown" id="menu-item-multiselect-dropdown">
            <div class="custom-multiselect-search-wrap">
                <input type="text" class="form-control form-control-sm" id="menu-item-search" placeholder="Search menu items..." autocomplete="off">
            </div>
            <div class="custom-multiselect-list" id="menu-item-multiselect-list">
                {{-- Dynamically populated from raw select options --}}
            </div>
        </div>
    </div>

    @error('menu_item_ids')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('menu_item_ids.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label fw-semibold mb-0" for="kitchen_ids">Kitchen Stations</label>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary" id="kitchen-select-all" style="font-size: 0.8rem;">Select all</button>
            <span class="text-muted" style="font-size: 0.8rem;">·</span>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-secondary" id="kitchen-clear-all" style="font-size: 0.8rem;">Clear</button>
        </div>
    </div>
    
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
            <div class="custom-multiselect-search-wrap">
                <input type="text" class="form-control form-control-sm" id="kitchen-search" placeholder="Search kitchen stations..." autocomplete="off">
            </div>
            <div class="custom-multiselect-list" id="kitchen-multiselect-list">
                {{-- Dynamically populated from raw select options --}}
            </div>
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
    padding: 0;
    display: none;
    overflow: hidden;
}
.custom-multiselect-dropdown.show {
    display: block;
}
.custom-multiselect-search-wrap {
    padding: 8px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.custom-multiselect-search-wrap input {
    font-size: 0.85rem;
}
.custom-multiselect-list {
    max-height: 220px;
    overflow-y: auto;
    padding: 4px 0;
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
    font-weight: 600;
    background-color: #f8fafc;
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
    function initMultiSelect(config) {
        const rawSelect = document.getElementById(config.rawSelectId);
        const container = document.getElementById(config.containerId);
        const trigger = document.getElementById(config.triggerId);
        const tagsWrapper = document.getElementById(config.tagsId);
        const dropdown = document.getElementById(config.dropdownId);
        const listWrapper = document.getElementById(config.listId);
        const searchInput = document.getElementById(config.searchId);
        const selectAllBtn = document.getElementById(config.selectAllId);
        const clearBtn = document.getElementById(config.clearId);

        if (!rawSelect || !container || !trigger || !tagsWrapper || !dropdown || !listWrapper) return;

        let filterTerm = '';

        function render() {
            tagsWrapper.innerHTML = '';
            listWrapper.innerHTML = '';

            const options = Array.from(rawSelect.options).filter(opt => opt.value !== '');

            if (options.length === 0) {
                listWrapper.innerHTML = `<div class="custom-multiselect-empty">${config.emptyText}</div>`;
                tagsWrapper.innerHTML = `<span class="custom-multiselect-placeholder">${config.placeholder}</span>`;
                return;
            }

            let hasSelected = false;

            // Render selected tags in trigger
            options.forEach(opt => {
                if (opt.selected) {
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
            });

            if (!hasSelected) {
                tagsWrapper.innerHTML = `<span class="custom-multiselect-placeholder">${config.placeholder}</span>`;
            }

            // Filter options for dropdown list
            const filtered = options.filter(opt => {
                if (!filterTerm) return true;
                return opt.textContent.toLowerCase().includes(filterTerm.toLowerCase());
            });

            if (filtered.length === 0) {
                listWrapper.innerHTML = '<div class="custom-multiselect-empty">No matching items</div>';
            } else {
                filtered.forEach(opt => {
                    const isSelected = opt.selected;
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

                    listWrapper.appendChild(item);
                });
            }

            // Tag remove buttons
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

        // Toggle open/close
        trigger.addEventListener('click', function () {
            const isOpen = dropdown.classList.contains('show');
            if (isOpen) {
                dropdown.classList.remove('show');
                trigger.classList.remove('is-open');
            } else {
                document.querySelectorAll('.custom-multiselect-dropdown.show').forEach(d => d.classList.remove('show'));
                document.querySelectorAll('.custom-multiselect-trigger.is-open').forEach(t => t.classList.remove('is-open'));
                dropdown.classList.add('show');
                trigger.classList.add('is-open');
                if (searchInput) {
                    setTimeout(() => searchInput.focus(), 50);
                }
            }
        });

        // Trigger keyboard support
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                trigger.click();
            }
        });

        // Search filter
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filterTerm = this.value.trim();
                render();
            });
            searchInput.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    filterTerm = '';
                    render();
                }
            });
        }

        // Select All
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                Array.from(rawSelect.options).forEach(opt => {
                    if (opt.value !== '') opt.selected = true;
                });
                trigger.classList.remove('is-invalid');
                rawSelect.dispatchEvent(new Event('change', { bubbles: true }));
                render();
            });
        }

        // Clear All
        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                Array.from(rawSelect.options).forEach(opt => {
                    opt.selected = false;
                });
                rawSelect.dispatchEvent(new Event('change', { bubbles: true }));
                render();
            });
        }

        // Click outside to close
        document.addEventListener('click', function (e) {
            if (!container.contains(e.target)) {
                dropdown.classList.remove('show');
                trigger.classList.remove('is-open');
            }
        });

        // Esc key to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                trigger.classList.remove('is-open');
            }
        });

        // Dynamic reload listeners
        rawSelect.addEventListener('options-reloaded', function () {
            if (searchInput) {
                searchInput.value = '';
                filterTerm = '';
            }
            render();
        });
        rawSelect.addEventListener('change', render);

        const observer = new MutationObserver(render);
        observer.observe(rawSelect, { childList: true });

        render();
    }

    // Initialize Menu Items Multi-select
    initMultiSelect({
        rawSelectId: 'menu_item_ids',
        containerId: 'menu-item-multiselect-container',
        triggerId: 'menu-item-multiselect-trigger',
        tagsId: 'menu-item-multiselect-tags',
        dropdownId: 'menu-item-multiselect-dropdown',
        listId: 'menu-item-multiselect-list',
        searchId: 'menu-item-search',
        selectAllId: 'menu-item-select-all',
        clearId: 'menu-item-clear-all',
        placeholder: 'Select menu items...',
        emptyText: 'No menu items available for this branch',
    });

    // Initialize Kitchen Stations Multi-select
    initMultiSelect({
        rawSelectId: 'kitchen_ids',
        containerId: 'kitchen-multiselect-container',
        triggerId: 'kitchen-multiselect-trigger',
        tagsId: 'kitchen-multiselect-tags',
        dropdownId: 'kitchen-multiselect-dropdown',
        listId: 'kitchen-multiselect-list',
        searchId: 'kitchen-search',
        selectAllId: 'kitchen-select-all',
        clearId: 'kitchen-clear-all',
        placeholder: 'Select kitchen stations...',
        emptyText: 'No kitchen stations available for this branch',
    });

    // Form submission validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            let hasError = false;
            ['menu_item_ids', 'kitchen_ids'].forEach(id => {
                const sel = document.getElementById(id);
                if (sel) {
                    const hasSelected = Array.from(sel.options).some(opt => opt.selected && opt.value !== '');
                    if (!hasSelected) {
                        hasError = true;
                        const trig = sel.closest('.mb-3')?.querySelector('.custom-multiselect-trigger');
                        if (trig) {
                            trig.classList.add('is-invalid');
                        }
                    }
                }
            });
            if (hasError) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.custom-multiselect-trigger.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});
</script>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => old('is_active', $mapping?->is_active ?? true)])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save Assignments</button>
    <a href="{{ route('kitchen-mappings.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
