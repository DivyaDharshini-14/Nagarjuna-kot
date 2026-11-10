<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\MenuType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuTypeController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $menuTypes = $this->applyCompanyScope(MenuType::query()->with(['company', 'branch']));

        if ($this->locksBranch()) {
            $menuTypes->where(function ($query) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $this->actor()->branch_id);
            });
        }

        $this->applySearch($menuTypes, $request, ['name']);
        $this->applyStatus($menuTypes, $request);

        $menuTypes = $menuTypes->orderBy('name')->paginate(10)->withQueryString();

        return view('menu-types.index', compact('menuTypes'));
    }

    public function create(): View
    {
        return view('menu-types.create', $this->formState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateMenuType($request);
        $this->assertTenant($validated, false);

        MenuType::create($validated);

        return redirect()->route('menu-types.index')->with('success', 'Menu type created successfully.');
    }

    public function edit(MenuType $menuType): View
    {
        $this->authorizeMenuType($menuType);

        return view('menu-types.edit', array_merge($this->formState($menuType), compact('menuType')));
    }

    public function update(Request $request, MenuType $menuType): RedirectResponse
    {
        $this->authorizeMenuType($menuType);
        $this->mergeLockedTenant($request);

        $validated = $this->validateMenuType($request, $menuType);
        $this->assertTenant($validated, false);

        if ($menuType->menuItems()->exists() && (
            (int) $menuType->company_id !== (int) $validated['company_id']
            || (int) ($menuType->branch_id ?? 0) !== (int) ($validated['branch_id'] ?? 0)
        )) {
            return back()->withErrors([
                'branch_id' => 'This menu type is used by menu items and its company or branch cannot be changed.',
            ])->withInput();
        }

        $menuType->update($validated);

        return redirect()->route('menu-types.index')->with('success', 'Menu type updated successfully.');
    }

    public function destroy(MenuType $menuType): RedirectResponse
    {
        $this->authorizeMenuType($menuType);

        if ($menuType->menuItems()->exists()) {
            return back()->with('error', 'This menu type is used by menu items and cannot be deleted.');
        }

        $menuType->delete();

        return redirect()->route('menu-types.index')->with('success', 'Menu type deleted successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $this->authorizeCompanyId((int) $data['company_id']);
        $branchId = $data['branch_id'] ?? ($this->locksBranch() ? $this->actor()->branch_id : null);

        if ($branchId) {
            $branch = Branch::query()->findOrFail($branchId);
            abort_unless((int) $branch->company_id === (int) $data['company_id'], 403);
            $this->authorizeBranch($branch);
        } elseif ($this->locksBranch()) {
            abort(403);
        }

        return response()->json($this->optionPayload(
            $this->menuTypeChoices((int) $data['company_id'], $branchId ? (int) $branchId : null)
        ));
    }

    private function authorizeMenuType(MenuType $menuType): void
    {
        $this->authorizeCompanyId((int) $menuType->company_id);

        if ($this->locksBranch()) {
            abort_unless((int) $menuType->branch_id === (int) $this->actor()->branch_id, 403);
        }
    }

    private function validateMenuType(Request $request, ?MenuType $menuType = null): array
    {
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $menuType?->company_id)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $menuType) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $menuType?->branch_id);
                }),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menu_types', 'name')->where('company_id', $request->input('company_id'))->ignore($menuType?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['branch_id'] = $validated['branch_id'] ?? null;

        return $validated;
    }
}
