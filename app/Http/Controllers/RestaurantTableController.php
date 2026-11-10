<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantTableController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $tables = $this->applyBranchScope(RestaurantTable::query()->with(['company', 'branch', 'floor']));
        $this->applySearch($tables, $request, ['name']);
        $this->applyStatus($tables, $request);

        $tables = $tables->orderBy('name')->paginate(10)->withQueryString();

        return view('tables.index', compact('tables'));
    }

    public function create(): View
    {
        $state = $this->formState();

        return view('tables.create', array_merge($state, [
            'floors' => $this->floorChoices(
                $state['selectedBranch'] ? (int) $state['selectedBranch'] : null
            ),
            'statuses' => $this->statuses(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateTable($request);
        $this->assertTenant($validated);

        RestaurantTable::create($validated);

        return redirect()->route('tables.index')->with('success', 'Table created successfully.');
    }

    public function edit(RestaurantTable $table): View
    {
        $this->authorizeBranch($table->branch);
        $state = $this->formState($table);

        return view('tables.edit', array_merge($state, [
            'table' => $table,
            'floors' => $this->floorChoices(
                $state['selectedBranch'] ? (int) $state['selectedBranch'] : null,
                (int) old('floor_id', $table->floor_id)
            ),
            'statuses' => $this->statuses(),
        ]));
    }

    public function update(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->authorizeBranch($table->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateTable($request, $table);
        $this->assertTenant($validated);

        $table->update($validated);

        return redirect()->route('tables.index')->with('success', 'Table updated successfully.');
    }

    public function destroy(RestaurantTable $table): RedirectResponse
    {
        $this->authorizeBranch($table->branch);
        $table->delete();

        return redirect()->route('tables.index')->with('success', 'Table deleted successfully.');
    }

    private function validateTable(Request $request, ?RestaurantTable $table = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $table?->company_id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $table) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $table?->branch_id);
                }),
            ],
            'floor_id' => [
                'required',
                'integer',
                Rule::exists('floors', 'id')->where(function ($query) use ($request, $table) {
                    $query->where('company_id', $request->input('company_id'))
                        ->where('branch_id', $request->input('branch_id'));
                    $this->limitToActiveOrCurrent($query, $table?->floor_id);
                }),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('restaurant_tables', 'name')->where('floor_id', $request->input('floor_id'))->ignore($table?->id),
            ],
            'seating_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'table_status' => ['required', Rule::in(array_keys($this->statuses()))],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function statuses(): array
    {
        return [
            'available' => 'Available',
            'occupied' => 'Occupied',
            'reserved' => 'Reserved',
            'cleaning' => 'Cleaning',
        ];
    }
}
