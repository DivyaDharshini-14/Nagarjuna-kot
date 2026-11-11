<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $state = $this->tableFormState();

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

        // Derive company_id automatically from selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorizeBranch($branch);
        $validated['company_id'] = $branch->company_id;

        RestaurantTable::create($validated);

        return redirect()->route('tables.index')->with('success', 'Table created successfully.');
    }

    public function edit(RestaurantTable $table): View
    {
        $this->authorizeBranch($table->branch);
        $state = $this->tableFormState($table);

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

        // Derive company_id automatically from selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorizeBranch($branch);
        $validated['company_id'] = $branch->company_id;

        if ($table->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists() && (
            (int) $table->branch_id !== (int) $validated['branch_id']
            || (int) $table->floor_id !== (int) $validated['floor_id']
        )) {
            return back()->withErrors([
                'branch_id' => 'This table has active orders and its location cannot be changed.',
            ])->withInput();
        }

        $table->update($validated);

        return redirect()->route('tables.index')->with('success', 'Table updated successfully.');
    }

    public function destroy(RestaurantTable $table): RedirectResponse
    {
        $this->authorizeBranch($table->branch);
        $table->delete();

        return redirect()->route('tables.index')->with('success', 'Table deleted successfully.');
    }

    private function tableFormState(?RestaurantTable $table = null): array
    {
        $selectedBranch = old('branch_id', $table?->branch_id ?? ($this->locksBranch() ? $this->actor()->branch_id : null));

        $branchQuery = Branch::query()->with('company');
        $this->applyBranchScope($branchQuery, 'company_id', 'id');

        $branches = $branchQuery->where(function ($query) use ($selectedBranch) {
            $query->where('is_active', true);
            if ($selectedBranch) {
                $query->orWhere('id', $selectedBranch);
            }
        })->orderBy('name')->get();

        return [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'lockBranch' => $this->locksBranch(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ];
    }

    private function validateTable(Request $request, ?RestaurantTable $table = null): array
    {
        $branchId = (int) $request->input('branch_id');

        // Prevent inactive floors from being selected when creating new tables
        if (! $table && $request->filled('floor_id')) {
            $selectedFloor = Floor::query()->find($request->input('floor_id'));
            if ($selectedFloor && ! $selectedFloor->is_active) {
                throw ValidationException::withMessages([
                    'floor_id' => ['The selected floor is inactive. Inactive floors cannot be assigned to new tables.'],
                ]);
            }
        }

        // Ensure the selected floor belongs to the selected branch
        if ($request->filled('floor_id') && $branchId) {
            $selectedFloor = Floor::query()->find($request->input('floor_id'));
            if ($selectedFloor && (int) $selectedFloor->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'floor_id' => ['The selected floor does not belong to the selected branch.'],
                ]);
            }
        }

        return $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($table) {
                    if (! $this->isSuperAdmin()) {
                        $query->where('company_id', $this->actor()->company_id);
                    }
                    $this->limitToActiveOrCurrent($query, $table?->branch_id);
                }),
            ],
            'floor_id' => [
                'required',
                'integer',
                Rule::exists('floors', 'id')->where(function ($query) use ($branchId, $table) {
                    $query->where('branch_id', $branchId);
                    if ($table?->floor_id) {
                        $this->limitToActiveOrCurrent($query, $table->floor_id);
                    } else {
                        // For new tables: strictly enforce active floor
                        $query->whereNull('deleted_at')->where('is_active', true);
                    }
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
        ], [
            'floor_id.exists' => 'The selected floor is invalid or does not belong to the selected branch.',
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
