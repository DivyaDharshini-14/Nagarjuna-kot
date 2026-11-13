@csrf

@include('partials.company-branch-fields', [
    'showBranch' => true,
    'branchRequired' => $lockBranch || $lockCompany,
])

<div class="mb-3">
    <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user?->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="employee_code">Employee Code</label>
        <input class="form-control font-monospace @error('employee_code') is-invalid @enderror" id="employee_code" name="employee_code" value="{{ old('employee_code', $user?->employee_code) }}" placeholder="e.g. EMP-101">
        @error('employee_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="designation">Designation</label>
        <input class="form-control @error('designation') is-invalid @enderror" id="designation" name="designation" value="{{ old('designation', $user?->designation) }}" placeholder="e.g. Head Chef / Captain / Waiter">
        @error('designation')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
    <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required>
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="phone">Phone Number</label>
    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user?->phone) }}">
    @error('phone')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="password">Password @if(isset($user)) <span class="text-muted">(Leave blank to keep current)</span> @else <span class="text-danger">*</span> @endif</label>
    <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" minlength="8" @if(!isset($user)) required @endif>
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label d-block">Assign Roles <span class="text-danger">*</span></label>
    @error('roles')
        <div class="alert alert-danger py-1 mb-2">{{ $message }}</div>
    @enderror
    <div class="row g-2 border rounded p-3 bg-white">
        @foreach($roles as $role)
            <div class="col-md-4 col-sm-6">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="roles[]"
                        value="{{ $role->id }}"
                        id="role_{{ $role->id }}"
                        @checked(in_array($role->id, old('roles', $userRoles ?? [])))
                    >
                    <label class="form-check-label" for="role_{{ $role->id }}">
                        <strong>{{ $role->name }}</strong>
                        @if($role->description)
                            <small class="d-block text-muted">{{ $role->description }}</small>
                        @endif
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

@php
    $assignedKitchenIds = old('kitchens', isset($user) && $user ? $user->kitchens->pluck('id')->all() : []);
@endphp

<div class="mb-3">
    <label class="form-label" for="kitchens">Assigned Kitchen Stations</label>
    <select
        class="form-select @error('kitchens') is-invalid @enderror"
        id="kitchens"
        name="kitchens[]"
        data-kitchen-select
        data-url="{{ route('kitchens.options') }}"
        data-placeholder="Choose kitchen stations"
        multiple
        size="4"
    >
        @foreach($kitchenChoices as $kitchen)
            <option value="{{ $kitchen->id }}" @selected(in_array($kitchen->id, $assignedKitchenIds))>{{ $kitchen->name }} ({{ $kitchen->code }})</option>
        @endforeach
    </select>
    @error('kitchens')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Choose one or more stations for kitchen staff. Options load after selecting the user's branch.</div>
</div>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active Account', 'checked' => $user?->is_active ?? true])

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">Save User</button>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

