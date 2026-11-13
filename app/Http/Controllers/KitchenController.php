<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Kitchen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KitchenController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $kitchens = $this->applyBranchScope(Kitchen::query()->with(['company', 'branch']));
        $this->applySearch($kitchens, $request, ['name', 'code', 'kitchen_type']);
        $this->applyStatus($kitchens, $request);

        $kitchens = $kitchens->latest()->paginate(10)->withQueryString();

        return view('kitchens.index', compact('kitchens'));
    }

    public function create(): View
    {
        return view('kitchens.create', $this->formState());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);
        $validated = $this->validateKitchen($request);
        $this->assertTenant($validated);

        Kitchen::create($validated);

        return redirect()->route('kitchens.index')->with('success', 'Kitchen created successfully.');
    }

    public function edit(Kitchen $kitchen): View
    {
        $this->authorizeBranch($kitchen->branch);

        return view('kitchens.edit', array_merge($this->formState($kitchen), compact('kitchen')));
    }

    public function update(Request $request, Kitchen $kitchen): RedirectResponse
    {
        $this->authorizeBranch($kitchen->branch);
        $this->mergeLockedTenant($request);

        $validated = $this->validateKitchen($request, $kitchen);
        $this->assertTenant($validated);

        if ($kitchen->kitchenMenuItems()->exists() && (
            (int) $kitchen->company_id !== (int) $validated['company_id']
            || (int) $kitchen->branch_id !== (int) $validated['branch_id']
        )) {
            return back()->withErrors([
                'branch_id' => 'This kitchen has menu mappings and its branch cannot be changed.',
            ])->withInput();
        }

        $kitchen->update($validated);

        return redirect()->route('kitchens.index')->with('success', 'Kitchen updated successfully.');
    }

    public function destroy(Kitchen $kitchen): RedirectResponse
    {
        $this->authorizeBranch($kitchen->branch);

        if ($kitchen->kitchenMenuItems()->exists()) {
            return back()->with('error', 'This kitchen is mapped to menu items and cannot be deleted.');
        }

        $kitchen->delete();

        return redirect()->route('kitchens.index')->with('success', 'Kitchen deleted successfully.');
    }

    public function registerDisplay(Request $request, Kitchen $kitchen): RedirectResponse
    {
        $this->authorizeBranch($kitchen->branch);
        abort_unless($request->user()->hasPermission('kitchen.edit'), 403);
        $token = Str::random(64);
        $kitchen->forceFill([
            'display_token_hash' => hash('sha256', $token),
            'display_token_created_at' => now(),
        ])->save();

        return back()->with('registered_display_url', route('kds.registered-display', $token));
    }

    public function revokeDisplay(Request $request, Kitchen $kitchen): RedirectResponse
    {
        $this->authorizeBranch($kitchen->branch);
        abort_unless($request->user()->hasPermission('kitchen.edit'), 403);
        $kitchen->forceFill(['display_token_hash' => null, 'display_token_created_at' => null])->save();

        return back()->with('success', 'Kitchen display access revoked.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->authorizeBranch($branch);

        return response()->json($this->optionPayload($this->kitchenChoices($branch->id)));
    }

    private function validateKitchen(Request $request, ?Kitchen $kitchen = null): array
    {
        return $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $this->limitToActiveOrCurrent($query, $kitchen?->company_id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($request, $kitchen) {
                    $query->where('company_id', $request->input('company_id'));
                    $this->limitToActiveOrCurrent($query, $kitchen?->branch_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('kitchens', 'code')->where('branch_id', $request->input('branch_id'))->ignore($kitchen?->id),
            ],
            'kitchen_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
