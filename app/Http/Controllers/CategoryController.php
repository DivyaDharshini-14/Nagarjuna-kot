<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Category;
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
        $categories = $this->scopedCategories()->with(['company', 'branch']);
        $this->applySearch($categories, $request, ['name', 'code']);
        $this->applyStatus($categories, $request);

        $categories = $categories->orderBy('display_order')->orderBy('name')->paginate(10)->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create', $this->formState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateCategory($request);
        $this->assertTenant($validated, false);

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        $this->authorizeCategory($category);

        return view('categories.edit', array_merge($this->formState($category), compact('category')));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);
        $this->mergeLockedTenant($request);

        $validated = $this->validateCategory($request, $category);
        $this->assertTenant($validated, false);

        if ($category->menuItems()->exists() && $this->locationChanged($category, $validated)) {
            return back()->withErrors([
                'branch_id' => 'This category is used by menu items and its company or branch cannot be changed.',
            ])->withInput();
        }

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
            $this->categoryChoices((int) $data['company_id'], $branchId ? (int) $branchId : null)
        ));
    }

    private function scopedCategories()
    {
        $query = $this->applyCompanyScope(Category::query());

        if ($this->locksBranch()) {
            $query->where(function ($query) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $this->actor()->branch_id);
            });
        }

        return $query;
    }

    private function authorizeCategory(Category $category): void
    {
        $this->authorizeCompanyId((int) $category->company_id);

        if ($this->locksBranch()) {
            abort_unless((int) $category->branch_id === (int) $this->actor()->branch_id, 403);
        }
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $category?->company_id)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $category) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $category?->branch_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('categories', 'code')->where('company_id', $request->input('company_id'))->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['branch_id'] = $validated['branch_id'] ?? null;

        return $validated;
    }

    private function locationChanged(Category $category, array $validated): bool
    {
        return (int) $category->company_id !== (int) $validated['company_id']
            || (int) ($category->branch_id ?? 0) !== (int) ($validated['branch_id'] ?? 0);
    }
}
