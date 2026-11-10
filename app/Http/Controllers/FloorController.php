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
        $this->applySearch($floors, $request, ['name', 'code']);
        $this->applyStatus($floors, $request);

        $floors = $floors->latest()->paginate(10)->withQueryString();

        return view('floors.index', compact('floors'));
    }

    public function create(): View
    {
        return view('floors.create', $this->formState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateFloor($request);
        $this->assertTenant($validated);

        Floor::create($validated);

        return redirect()->route('floors.index')->with('success', 'Floor created successfully.');
    }

    public function edit(Floor $floor): View
    {
        $this->authorizeBranch($floor->branch);

        return view('floors.edit', array_merge($this->formState($floor), compact('floor')));
    }

    public function update(Request $request, Floor $floor): RedirectResponse
    {
        $this->authorizeBranch($floor->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateFloor($request, $floor);
        $this->assertTenant($validated);

        if ($floor->restaurantTables()->exists() && (
            (int) $floor->company_id !== (int) $validated['company_id']
            || (int) $floor->branch_id !== (int) $validated['branch_id']
        )) {
            return back()->withErrors([
                'branch_id' => 'This floor has tables and its branch cannot be changed.',
            ])->withInput();
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

    private function validateFloor(Request $request, ?Floor $floor = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $floor?->company_id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $floor) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $floor?->branch_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('floors', 'code')->where('branch_id', $request->input('branch_id'))->ignore($floor?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
