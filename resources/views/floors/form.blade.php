@csrf
<div class="mb-3">
    <label class="form-label" for="branch_id">Branch</label>
    @if($lockBranch)
        <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
    @endif
    <select
        id="branch_id"
        class="form-select @error('branch_id') is-invalid @enderror"
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
    <label class="form-label" for="name">Floor name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $floor?->name) }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $floor?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@include('partials.checkbox', ['name' => 'is_active', 'label' => 'Active', 'checked' => $floor?->is_active ?? true])

<div class="d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="{{ route('floors.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
