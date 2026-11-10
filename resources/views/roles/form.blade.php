@csrf

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label" for="name">Role Name <span class="text-danger">*</span></label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $role->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="slug">Role Slug <span class="text-danger">*</span></label>
        <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $role->slug ?? '') }}" @if(!empty($isProtected)) readonly @endif required>
        @if(!empty($isProtected))
            <small class="text-muted">Slug is locked for core system roles.</small>
        @else
            <small class="text-muted">Lowercase alphanumeric with hyphens (e.g. branch-manager).</small>
        @endif
        @error('slug')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2">{{ old('description', $role->description ?? '') }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        @include('partials.checkbox', [
            'name' => 'is_active',
            'label' => 'Active Role',
            'checked' => $role->is_active ?? true,
        ])
    </div>
</div>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Module Permissions</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllPermissions">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllPermissions">Deselect All</button>
        </div>
    </div>

    <div class="row g-3">
        @foreach($permissionsByModule as $module => $permissions)
            <div class="col-lg-6">
                <div class="card h-100 border">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                        <strong class="text-uppercase small text-dark">{{ ucfirst(str_replace('-', ' ', $module)) }} Module</strong>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 toggle-module-btn" data-module="{{ $module }}">Toggle</button>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            @foreach($permissions as $permission)
                                <div class="col-6">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input perm-checkbox perm-module-{{ $module }}"
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->id }}"
                                            id="perm_{{ $permission->id }}"
                                            @checked(in_array($permission->id, old('permissions', $rolePermissions ?? [])))
                                        >
                                        <label class="form-check-label small" for="perm_{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Save Role & Permissions</button>
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllBtn = document.getElementById('selectAllPermissions');
    const deselectAllBtn = document.getElementById('deselectAllPermissions');
    const allCheckboxes = document.querySelectorAll('.perm-checkbox');

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            allCheckboxes.forEach(cb => cb.checked = true);
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            allCheckboxes.forEach(cb => cb.checked = false);
        });
    }

    document.querySelectorAll('.toggle-module-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const module = this.dataset.module;
            const moduleCheckboxes = document.querySelectorAll('.perm-module-' + module);
            const allChecked = Array.from(moduleCheckboxes).every(cb => cb.checked);
            moduleCheckboxes.forEach(cb => cb.checked = !allChecked);
        });
    });
});
</script>

