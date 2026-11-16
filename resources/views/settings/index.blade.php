@extends('layouts.app')
@section('title', 'Branch settings')
@section('content')
    <h1 class="h3 mb-3">Branch settings</h1>
    <form method="GET" class="mb-4 d-flex gap-2 align-items-end">
        <div><label class="form-label" for="branch-picker">Branch</label>
            <select id="branch-picker" name="branch_id" class="form-select">
                @foreach($branches as $choice)
                    <option value="{{ $choice->id }}" @selected($branch?->id === $choice->id)>{{ $choice->name }} ({{ $choice->code }})</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-primary">Load settings</button>
    </form>
    @if($branch)
        <p class="text-muted">These settings apply to {{ $branch->name }}. Existing orders and tickets keep their numbers. Sound can also be muted by each kitchen operator.</p>
        <form method="POST" action="{{ route('settings.update', $branch) }}" class="card card-body">
            @csrf @method('PUT')
            <fieldset @disabled(!auth()->user()->hasPermission('settings.edit'))>
                <h2 class="h5">Order and KOT numbers</h2>
                <p class="text-muted small">Order numbers use a global sequence. KOT numbers use a branch sequence, with an optional daily reset. Counter values cannot be reset manually.</p>
                <div class="row g-3 mb-4">
                    @foreach(['order' => 'Order', 'kot' => 'KOT'] as $key => $label)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $key }}_prefix">{{ $label }} prefix</label>
                            <input id="{{ $key }}_prefix" name="{{ $key }}_prefix" class="form-control" value="{{ old($key.'_prefix', $values[$key.'_prefix']) }}" maxlength="20" pattern="[A-Za-z0-9_-]+" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $key }}_digits">{{ $label }} minimum number digits</label>
                            <input id="{{ $key }}_digits" name="{{ $key }}_digits" type="number" min="1" max="10" class="form-control" value="{{ old($key.'_digits', $values[$key.'_digits']) }}" required>
                        </div>
                    @endforeach
                </div>
                @include('partials.checkbox', ['name' => 'kot_daily_reset', 'label' => 'Start a new KOT sequence each day', 'checked' => $values['kot_daily_reset']])
                <h2 class="h5 mt-4">Kitchen stock and notifications</h2>
                <div class="mb-3">
                    <label class="form-label" for="low_stock_threshold">Low stock warning threshold in portions</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="1" max="99999" class="form-control" value="{{ old('low_stock_threshold', $values['low_stock_threshold']) }}" required>
                </div>
                @include('partials.checkbox', ['name' => 'sound_enabled', 'label' => 'Allow incoming and cancelled KOT sound alerts', 'checked' => $values['sound_enabled']])
                <h2 class="h5 mt-4">Cancellation policy</h2>
                @include('partials.checkbox', ['name' => 'cancellation_approval_required', 'label' => 'Require approval by a different authorized user for every cancellation', 'checked' => $branch->cancellation_approval_required])
                <p class="text-muted small">Stock is restored only when none of the assigned kitchens has started preparation. Prepared or served cancellations are recorded as wastage.</p>
                <button class="btn btn-primary mt-2">Save settings</button>
            </fieldset>
        </form>
        <p class="mt-3">Company details, branch details, dish stock tracking and display registration are managed from their existing administration pages.</p>
    @else
        <p>No active branch is available for your account.</p>
    @endif
@endsection
