<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $branches = $this->applyBranchScope(Branch::query()->with('company'), 'company_id', 'id');
        $this->applySearch($branches, $request, ['name', 'code', 'email', 'phone']);
        $this->applyStatus($branches, $request);

        $branches = $branches->latest()->paginate(10)->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        abort_if($this->locksBranch(), 403);

        return view('branches.create', $this->formState());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($this->locksBranch(), 403);
        $this->mergeLockedTenant($request);

        $validated = $this->validateBranch($request);
        $this->authorizeCompanyId((int) $validated['company_id']);

        Branch::create($validated);

        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorizeBranch($branch);

        return view('branches.edit', array_merge($this->formState($branch), compact('branch')));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateBranch($request, $branch);
        $this->authorizeCompanyId((int) $validated['company_id']);

        if ((int) $branch->company_id !== (int) $validated['company_id'] && $this->hasDependencies($branch)) {
            return back()->withErrors([
                'company_id' => 'This branch has related records and its company cannot be changed.',
            ])->withInput();
        }

        $branch->update($validated);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);

        if ($this->hasDependencies($branch)) {
            return back()->with('error', 'This branch has related records and cannot be deleted.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        return response()->json($this->optionPayload(
            $this->branchChoices((int) $data['company_id'])
        ));
    }

    private function validateBranch(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $branch?->company_id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('branches', 'code')
                    ->where('company_id', $request->input('company_id'))
                    ->ignore($branch?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'cancellation_approval_required' => ['required', 'boolean'],
        ]);
    }

    private function hasDependencies(Branch $branch): bool
    {
        return $branch->kitchens()->exists()
            || $branch->floors()->exists()
            || $branch->users()->exists()
            || $branch->restaurantTables()->exists()
            || $branch->categories()->exists()
            || $branch->menuTypes()->exists()
            || $branch->menuItems()->exists()
            || $branch->kitchenMenuItems()->exists();
    }
}
