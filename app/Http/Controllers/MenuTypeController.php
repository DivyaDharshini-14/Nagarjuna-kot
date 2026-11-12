<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Company;
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
        $menuTypes = $this->applyCompanyScope(MenuType::query()->with('company'));
        $this->applySearch($menuTypes, $request, ['name']);
        $this->applyStatus($menuTypes, $request);

        $menuTypes = $menuTypes->orderBy('name')->paginate(10)->withQueryString();

        return view('menu-types.index', compact('menuTypes'));
    }

    public function create(): View
    {
        return view('menu-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $companyId = $this->resolveCompanyId($request);
        $request->merge(['company_id' => $companyId]);

        $validated = $this->validateMenuType($request);
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = null; // Maintained at company level, shared across all branches

        $this->authorizeCompanyId((int) $validated['company_id']);

        MenuType::create($validated);

        return redirect()->route('menu-types.index')->with('success', 'Menu type created successfully.');
    }

    public function edit(MenuType $menuType): View
    {
        $this->authorizeMenuType($menuType);

        return view('menu-types.edit', compact('menuType'));
    }

    public function update(Request $request, MenuType $menuType): RedirectResponse
    {
        $this->authorizeMenuType($menuType);
        $this->mergeLockedTenant($request);

        $companyId = $this->resolveCompanyId($request, $menuType);
        $request->merge(['company_id' => $companyId]);

        $validated = $this->validateMenuType($request, $menuType);
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = null; // Maintained at company level, shared across all branches

        $this->authorizeCompanyId((int) $validated['company_id']);

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
        $companyId = $request->input('company_id') ?: $this->actor()->company_id;
        if (! $companyId) {
            $companyId = Company::where('is_active', true)->value('id');
        }

        if ($companyId) {
            $this->authorizeCompanyId((int) $companyId);
        }

        // Return only active menu types for dropdown selection
        return response()->json($this->optionPayload(
            $this->menuTypeChoices($companyId ? (int) $companyId : null)
        ));
    }

    private function authorizeMenuType(MenuType $menuType): void
    {
        $this->authorizeCompanyId((int) $menuType->company_id);
    }

    private function resolveCompanyId(Request $request, ?MenuType $menuType = null): int
    {
        if ($this->actor()->company_id) {
            return (int) $this->actor()->company_id;
        }

        if ($menuType?->company_id) {
            return (int) $menuType->company_id;
        }

        if ($request->filled('company_id')) {
            return (int) $request->input('company_id');
        }

        $firstCompanyId = Company::query()->where('is_active', true)->value('id');
        abort_unless($firstCompanyId, 422, 'No active company found to assign menu type.');

        return (int) $firstCompanyId;
    }

    private function validateMenuType(Request $request, ?MenuType $menuType = null): array
    {
        $companyId = (int) $request->input('company_id');

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menu_types', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($menuType?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
