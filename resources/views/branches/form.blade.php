@csrf
@include('partials.company-branch-fields', ['showBranch' => false])
<div class="mb-3">
    <label class="form-label" for="name">Branch name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $branch?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="code">Branch code</label>
    <input class="form-control" id="code" name="code" value="{{ old('code', $branch?->code) }}" required>
</div>
<div class="mb-3 d-none">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $branch?->email) }}">
</div>
<div class="mb-3">
    <label class="form-label" for="phone">Contact number</label>
    <input class="form-control" id="phone" name="phone" value="{{ old('phone', $branch?->phone) }}">
</div>
<div class="mb-3">
    <label class="form-label" for="address">Address</label>
    <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $branch?->address) }}</textarea>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $branch?->is_active ?? true])
@include('partials.checkbox', [
    'name' => 'cancellation_approval_required',
    'label' => 'Require manager approval for all cancellations, including requests from approvers',
    'checked' => $branch?->cancellation_approval_required ?? false,
])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
