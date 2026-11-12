<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\KitchenMenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KitchenMappingController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $mappings = $this->applyBranchScope(KitchenMenuItem::query()->with(['company', 'branch', 'menuItem', 'kitchen']));

        $term = trim((string) $request->search);

        if ($term !== '') {
            $mappings->where(function ($query) use ($term) {
                $query->whereHas('menuItem', function ($query) use ($term) {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%');
                })->orWhereHas('kitchen', function ($query) use ($term) {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%');
                });
            });
        }

        $this->applyStatus($mappings, $request);

        $mappings = $mappings->latest()->paginate(10)->withQueryString();

        return view('kitchen-mappings.index', compact('mappings'));
    }

    public function create(): View
    {
        return view('kitchen-mappings.create', $this->selectionState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateMapping($request);

        // Derive company_id from selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorizeBranch($branch);
        $companyId = $branch->company_id;

        $menuItemId = (int) $validated['menu_item_id'];
        $kitchenIds = array_unique(array_map('intval', $validated['kitchen_ids']));
        $isActive = (bool) $validated['is_active'];

        // Save all selected kitchen assignments together in a transaction
        DB::transaction(function () use ($companyId, $branch, $menuItemId, $kitchenIds, $isActive) {
            foreach ($kitchenIds as $kitchenId) {
                $mapping = KitchenMenuItem::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->where('menu_item_id', $menuItemId)
                    ->where('kitchen_id', $kitchenId)
                    ->first();

                if ($mapping) {
                    if ($mapping->trashed()) {
                        $mapping->restore();
                    }
                    $mapping->update([
                        'company_id' => $companyId,
                        'is_active' => $isActive,
                    ]);
                } else {
                    KitchenMenuItem::create([
                        'company_id' => $companyId,
                        'branch_id' => $branch->id,
                        'menu_item_id' => $menuItemId,
                        'kitchen_id' => $kitchenId,
                        'is_active' => $isActive,
                    ]);
                }
            }
        });

        $count = count($kitchenIds);

        return redirect()->route('kitchen-mappings.index')
            ->with('success', "Assigned dish to {$count} kitchen station(s) successfully.");
    }

    public function edit(KitchenMenuItem $kitchenMapping): View
    {
        $this->authorizeBranch($kitchenMapping->branch);

        return view('kitchen-mappings.edit', array_merge(
            $this->selectionState($kitchenMapping),
            ['mapping' => $kitchenMapping]
        ));
    }

    public function update(Request $request, KitchenMenuItem $kitchenMapping): RedirectResponse
    {
        $this->authorizeBranch($kitchenMapping->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateMapping($request, $kitchenMapping);

        // Derive company_id from selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorizeBranch($branch);
        $companyId = $branch->company_id;

        $menuItemId = (int) $validated['menu_item_id'];
        $newKitchenIds = array_unique(array_map('intval', $validated['kitchen_ids']));
        $isActive = (bool) $validated['is_active'];

        DB::transaction(function () use ($companyId, $branch, $menuItemId, $newKitchenIds, $isActive, $kitchenMapping) {
            $oldBranchId = $kitchenMapping->branch_id;
            $oldMenuItemId = $kitchenMapping->menu_item_id;

            // Remove assignments for kitchens that were unselected for this dish
            KitchenMenuItem::where('branch_id', $branch->id)
                ->where('menu_item_id', $menuItemId)
                ->whereNotIn('kitchen_id', $newKitchenIds)
                ->delete();

            // If the user changed the menu item or branch, remove previous mappings for old dish
            if ($oldMenuItemId !== $menuItemId || $oldBranchId !== $branch->id) {
                KitchenMenuItem::where('branch_id', $oldBranchId)
                    ->where('menu_item_id', $oldMenuItemId)
                    ->delete();
            }

            // Save/update all selected kitchen assignments together
            foreach ($newKitchenIds as $kitchenId) {
                $mapping = KitchenMenuItem::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->where('menu_item_id', $menuItemId)
                    ->where('kitchen_id', $kitchenId)
                    ->first();

                if ($mapping) {
                    if ($mapping->trashed()) {
                        $mapping->restore();
                    }
                    $mapping->update([
                        'company_id' => $companyId,
                        'is_active' => $isActive,
                    ]);
                } else {
                    KitchenMenuItem::create([
                        'company_id' => $companyId,
                        'branch_id' => $branch->id,
                        'menu_item_id' => $menuItemId,
                        'kitchen_id' => $kitchenId,
                        'is_active' => $isActive,
                    ]);
                }
            }
        });

        return redirect()->route('kitchen-mappings.index')
            ->with('success', 'Kitchen mappings updated successfully.');
    }

    public function destroy(KitchenMenuItem $kitchenMapping): RedirectResponse
    {
        $this->authorizeBranch($kitchenMapping->branch);
        $kitchenMapping->delete();

        return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping deleted successfully.');
    }

    private function selectionState(?KitchenMenuItem $mapping = null): array
    {
        $selectedBranch = old('branch_id', $mapping?->branch_id ?? ($this->locksBranch() ? $this->actor()->branch_id : null));

        $branchQuery = Branch::query()->with('company');
        $this->applyBranchScope($branchQuery, 'company_id', 'id');

        $branches = $branchQuery->where(function ($query) use ($selectedBranch) {
            $query->where('is_active', true);
            if ($selectedBranch) {
                $query->orWhere('id', $selectedBranch);
            }
        })->orderBy('name')->get();

        $branchId = $selectedBranch ? (int) $selectedBranch : null;

        $selectedKitchenIds = [];
        if (old('kitchen_ids') !== null) {
            $selectedKitchenIds = (array) old('kitchen_ids');
        } elseif ($mapping) {
            $selectedKitchenIds = KitchenMenuItem::where('branch_id', $mapping->branch_id)
                ->where('menu_item_id', $mapping->menu_item_id)
                ->pluck('kitchen_id')
                ->all();
        }

        return [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'menuItems' => $this->menuItemChoices($branchId, $mapping ? (int) old('menu_item_id', $mapping->menu_item_id) : null),
            'kitchens' => $this->kitchenChoices($branchId),
            'selectedKitchenIds' => $selectedKitchenIds,
            'lockBranch' => $this->locksBranch(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ];
    }

    private function validateMapping(Request $request, ?KitchenMenuItem $mapping = null): array
    {
        $branchId = (int) $request->input('branch_id');

        return $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($mapping) {
                    if (! $this->isSuperAdmin()) {
                        $query->where('company_id', $this->actor()->company_id);
                    }
                    $this->limitToActiveOrCurrent($query, $mapping?->branch_id);
                }),
            ],
            'menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(function ($query) use ($branchId, $mapping) {
                    $query->where('branch_id', $branchId);
                    $this->limitToActiveOrCurrent($query, $mapping?->menu_item_id);
                }),
            ],
            'kitchen_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'kitchen_ids.*' => [
                'required',
                'integer',
                Rule::exists('kitchens', 'id')->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)->where('is_active', true);
                }),
            ],
            'is_active' => ['required', 'boolean'],
        ], [
            'branch_id.required' => 'Please select a branch.',
            'menu_item_id.required' => 'Please select a menu item.',
            'menu_item_id.exists' => 'The selected menu item does not belong to the selected branch.',
            'kitchen_ids.required' => 'Please select at least one kitchen station.',
            'kitchen_ids.min' => 'Please select at least one kitchen station.',
            'kitchen_ids.*.exists' => 'One or more selected kitchen stations are invalid or do not belong to the selected branch.',
        ]);
    }
}
