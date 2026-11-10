@csrf
<div class="mb-3">
    <label class="form-label" for="name">Company name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $company?->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="code">Company code</label>
    <input class="form-control" id="code" name="code" value="{{ old('code', $company?->code) }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $company?->email) }}">
</div>
<div class="mb-3">
    <label class="form-label" for="phone">Contact number</label>
    <input class="form-control" id="phone" name="phone" value="{{ old('phone', $company?->phone) }}">
</div>
<div class="mb-3">
    <label class="form-label" for="address">Address</label>
    <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $company?->address) }}</textarea>
</div>
@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $company?->is_active ?? true])
<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
