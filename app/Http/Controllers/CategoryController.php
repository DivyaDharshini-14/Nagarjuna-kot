<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $categories = $this->applyCompanyScope(Category::query()->with('company'));
        $this->applySearch($categories, $request, ['name', 'code']);
        $this->applyStatus($categories, $request);

        $categories = $categories->orderBy('display_order')->orderBy('name')->paginate(10)->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $companyId = $this->resolveCompanyId($request);
        $request->merge(['company_id' => $companyId]);

        $validated = $this->validateCategory($request);
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = null; // Share at company level across all branches

        $this->authorizeCompanyId((int) $validated['company_id']);

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        $this->authorizeCategory($category);

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);
        $this->mergeLockedTenant($request);

        $companyId = $this->resolveCompanyId($request, $category);
        $request->merge(['company_id' => $companyId]);

        $validated = $this->validateCategory($request, $category);
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = null; // Share at company level across all branches

        $this->authorizeCompanyId((int) $validated['company_id']);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        if ($category->menuItems()->exists()) {
            return back()->with('error', 'This category is used by menu items and cannot be deleted.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted successfully.');
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

        return response()->json($this->optionPayload(
            $this->categoryChoices($companyId ? (int) $companyId : null)
        ));
    }

    private function authorizeCategory(Category $category): void
    {
        $this->authorizeCompanyId((int) $category->company_id);
    }

    private function resolveCompanyId(Request $request, ?Category $category = null): int
    {
        if ($this->actor()->company_id) {
            return (int) $this->actor()->company_id;
        }

        if ($category?->company_id) {
            return (int) $category->company_id;
        }

        if ($request->filled('company_id')) {
            return (int) $request->input('company_id');
        }

        $firstCompanyId = Company::query()->where('is_active', true)->value('id');
        abort_unless($firstCompanyId, 422, 'No active company found to assign category.');

        return (int) $firstCompanyId;
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $companyId = (int) $request->input('company_id');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('categories', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
