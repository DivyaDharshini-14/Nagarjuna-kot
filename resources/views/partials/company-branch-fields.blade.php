@php
    $showBranch = $showBranch ?? true;
    $branchRequired = $branchRequired ?? true;
    $companyValue = old('company_id', $selectedCompany);
    $branchValue = old('branch_id', $selectedBranch);
@endphp

<div class="mb-3">
    <label class="form-label" for="company_id">Company</label>
    @if($lockCompany)
        <input type="hidden" name="company_id" value="{{ $companyValue }}">
    @endif
    <select id="company_id" class="form-select" data-company-select @unless($lockCompany) name="company_id" required @else disabled @endunless>
        <option value="">Select company</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" @selected((string) $companyValue === (string) $company->id)>{{ $company->name }}</option>
        @endforeach
    </select>
</div>

@if($showBranch)
    <div class="mb-3">
        <label class="form-label" for="branch_id">Branch @unless($branchRequired)<span class="text-muted">(optional)</span>@endunless</label>
        @if($lockBranch)
            <input type="hidden" name="branch_id" value="{{ $branchValue }}">
        @endif
        <select
            id="branch_id"
            class="form-select"
            data-branch-select
            data-url="{{ route('branches.options') }}"
            data-placeholder="{{ $branchRequired ? 'Select branch' : 'All branches' }}"
            @unless($lockBranch) name="branch_id" @if($branchRequired) required @endif @else disabled @endunless
        >
            <option value="">{{ $branchRequired ? 'Select branch' : 'All branches' }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) $branchValue === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @unless($branchRequired)
            <div class="form-text">Leave this empty to make the record available to every branch in the company.</div>
        @endunless
    </div>
@endif
