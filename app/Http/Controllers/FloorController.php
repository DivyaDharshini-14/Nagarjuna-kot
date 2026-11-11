<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Floor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $floors = $this->applyBranchScope(Floor::query()->with(['company', 'branch']));
        $this->applySearch($floors, $request, ['name']);
        $this->applyStatus($floors, $request);

        $floors = $floors->latest()->paginate(10)->withQueryString();

        return view('floors.index', compact('floors'));
    }

    public function create(): View
    {
        return view('floors.create', $this->floorFormState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateFloor($request);

        // Automatically derive company_id from the selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);

        // Ensure the selected branch belongs to the logged-in user's permitted company
        $this->authorizeBranch($branch);
        $validated['company_id'] = $branch->company_id;

        // Auto-generate floor code in the background if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateFloorCode($validated['name'], $branch->id);
        }

        Floor::create($validated);

        return redirect()->route('floors.index')->with('success', 'Floor created successfully.');
    }

    public function edit(Floor $floor): View
    {
        $this->authorizeBranch($floor->branch);

        return view('floors.edit', array_merge($this->floorFormState($floor), compact('floor')));
    }

    public function update(Request $request, Floor $floor): RedirectResponse
    {
        $this->authorizeBranch($floor->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateFloor($request, $floor);

        // Automatically derive company_id from the selected branch
        $branch = Branch::query()->findOrFail($validated['branch_id']);

        // Ensure the selected branch belongs to the logged-in user's permitted company
        $this->authorizeBranch($branch);
        $validated['company_id'] = $branch->company_id;

        if ($floor->restaurantTables()->exists() && (
            (int) $floor->branch_id !== (int) $validated['branch_id']
        )) {
            return back()->withErrors([
                'branch_id' => 'This floor has tables and its branch cannot be changed.',
            ])->withInput();
        }

        // Keep existing code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $floor->code ?: $this->generateFloorCode($validated['name'], $branch->id, $floor->id);
        }

        $floor->update($validated);

        return redirect()->route('floors.index')->with('success', 'Floor updated successfully.');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        $this->authorizeBranch($floor->branch);

        if ($floor->restaurantTables()->exists()) {
            return back()->with('error', 'This floor has tables and cannot be deleted.');
        }

        $floor->delete();

        return redirect()->route('floors.index')->with('success', 'Floor deleted successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->authorizeBranch($branch);

        return response()->json($this->optionPayload($this->floorChoices($branch->id)));
    }

    private function floorFormState(?Floor $floor = null): array
    {
        $selectedBranch = old('branch_id', $floor?->branch_id ?? ($this->locksBranch() ? $this->actor()->branch_id : null));

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

    private function validateFloor(Request $request, ?Floor $floor = null): array
    {
        return $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($floor) {
                    if (! $this->isSuperAdmin()) {
                        $query->where('company_id', $this->actor()->company_id);
                    }
                    $this->limitToActiveOrCurrent($query, $floor?->branch_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function generateFloorCode(string $name, int $branchId, ?int $ignoreId = null): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $base = 'FL-' . substr($clean ?: '01', 0, 10);
        $candidate = $base;
        $counter = 1;

        while (
            Floor::where('branch_id', $branchId)
                ->where('code', $candidate)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = substr($base, 0, 7) . '-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $candidate;
    }
}
