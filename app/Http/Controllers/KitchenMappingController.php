<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\KitchenMenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $this->assertTenant($validated);

        $existing = KitchenMenuItem::withTrashed()
            ->where('menu_item_id', $validated['menu_item_id'])
            ->where('kitchen_id', $validated['kitchen_id'])
            ->first();

        if ($existing?->trashed()) {
            $existing->restore();
            $existing->update($validated);

            return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping restored successfully.');
        }

        KitchenMenuItem::create($validated);

        return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping created successfully.');
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
        $this->assertTenant($validated);

        $conflict = KitchenMenuItem::withTrashed()
            ->where('menu_item_id', $validated['menu_item_id'])
            ->where('kitchen_id', $validated['kitchen_id'])
            ->whereKeyNot($kitchenMapping->id)
            ->first();

        if ($conflict?->trashed()) {
            $conflict->restore();
            $conflict->update($validated);
            $kitchenMapping->delete();

            return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping updated successfully.');
        }

        $kitchenMapping->update($validated);

        return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping updated successfully.');
    }

    public function destroy(KitchenMenuItem $kitchenMapping): RedirectResponse
    {
        $this->authorizeBranch($kitchenMapping->branch);
        $kitchenMapping->delete();

        return redirect()->route('kitchen-mappings.index')->with('success', 'Kitchen mapping deleted successfully.');
    }

    private function selectionState(?KitchenMenuItem $mapping = null): array
    {
        $state = $this->formState($mapping);
        $branchId = $state['selectedBranch'] ? (int) $state['selectedBranch'] : null;

        return array_merge($state, [
            'menuItems' => $this->menuItemChoices($branchId, $mapping ? (int) old('menu_item_id', $mapping->menu_item_id) : null),
            'kitchens' => $this->kitchenChoices($branchId, $mapping ? (int) old('kitchen_id', $mapping->kitchen_id) : null),
        ]);
    }

    private function validateMapping(Request $request, ?KitchenMenuItem $mapping = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $mapping?->company_id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $mapping) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $mapping?->branch_id);
                }),
            ],
            'menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(function ($query) use ($request, $mapping) {
                    $query->where('company_id', $request->input('company_id'))
                        ->where('branch_id', $request->input('branch_id'));
                    $this->limitToActiveOrCurrent($query, $mapping?->menu_item_id);
                }),
            ],
            'kitchen_id' => [
                'required',
                'integer',
                Rule::exists('kitchens', 'id')->where(function ($query) use ($request, $mapping) {
                    $query->where('company_id', $request->input('company_id'))
                        ->where('branch_id', $request->input('branch_id'));
                    $this->limitToActiveOrCurrent($query, $mapping?->kitchen_id);
                }),
                Rule::unique('kitchen_menu_items', 'kitchen_id')
                    ->where('menu_item_id', $request->input('menu_item_id'))
                    ->whereNull('deleted_at')
                    ->ignore($mapping?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
