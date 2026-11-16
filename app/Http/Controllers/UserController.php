<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View
    {
        $users = User::query()->with(['company', 'branch', 'roles']);

        // Scope to branch / company if locked
        if ($this->locksBranch()) {
            $users->where('branch_id', $this->actor()->branch_id)
                ->where('company_id', $this->actor()->company_id);
        } elseif ($this->locksCompany()) {
            $users->where('company_id', $this->actor()->company_id);
        }

        // Non-admins cannot see administrator accounts
        if (! $this->isSuperAdmin()) {
            $users->whereDoesntHave('roles', function (Builder $query) {
                $query->whereIn('slug', ['admin', 'super-admin']);
            });
        }

        // Filter by company/branch if admin selects them
        if ($this->isSuperAdmin() && $request->filled('company_id')) {
            $users->where('company_id', $request->company_id);
        }
        if ($this->isSuperAdmin() && $request->filled('branch_id')) {
            $users->where('branch_id', $request->branch_id);
        }

        // Filter by role
        if ($request->filled('role')) {
            $users->whereHas('roles', function (Builder $query) use ($request) {
                $query->where('roles.slug', $request->role);
            });
        }

        $this->applySearch($users, $request, ['name', 'email', 'phone', 'employee_code', 'designation']);
        $this->applyStatus($users, $request);

        $users = $users->latest()->paginate(10)->withQueryString();

        $roles = $this->assignableRoles();
        $companies = $this->isSuperAdmin() ? Company::where('is_active', true)->orderBy('name')->get() : collect();
        $branches = $this->isSuperAdmin() && $request->filled('company_id')
            ? Branch::where('company_id', $request->company_id)->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('users.index', compact('users', 'roles', 'companies', 'branches'));
    }

    public function create(): View
    {
        $state = $this->formState();
        $roles = $this->assignableRoles();
        $kitchenChoices = $this->kitchenAssignmentChoices(
            $state['selectedCompany'] ? (int) $state['selectedCompany'] : null,
            $state['selectedBranch'] ? (int) $state['selectedBranch'] : null,
        );

        return view('users.create', array_merge($state, [
            'roles' => $roles,
            'userRoles' => [],
            'kitchenChoices' => $kitchenChoices,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);

        $validated = $this->validateUserData($request);

        $this->authorizeUserRoleAssignment($validated['roles']);

        if (! empty($validated['branch_id'])) {
            $branch = Branch::findOrFail($validated['branch_id']);
            $this->authorizeBranch($branch);
        } elseif (! empty($validated['company_id'])) {
            $this->authorizeCompanyId((int) $validated['company_id']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'employee_code' => $validated['employee_code'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'company_id' => $validated['company_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->roles()->sync($validated['roles']);
        if ($request->has('kitchens')) {
            $user->kitchens()->sync($this->selectedRolesIncludeKitchenStaff($validated['roles'])
                ? ($validated['kitchens'] ?? [])
                : []);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $this->authorizeUserAccess($user);

        $state = $this->formState($user);
        $roles = $this->assignableRoles();
        $userRoles = $user->roles->pluck('id')->toArray();
        $user->loadMissing('kitchens');
        $kitchenChoices = $this->kitchenAssignmentChoices(
            $user->company_id,
            $user->branch_id,
        );

        return view('users.edit', array_merge($state, [
            'user' => $user,
            'roles' => $roles,
            'userRoles' => $userRoles,
            'kitchenChoices' => $kitchenChoices,
        ]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserAccess($user);
        $this->mergeLockedTenant($request);

        $validated = $this->validateUserData($request, $user);

        $this->authorizeUserRoleAssignment($validated['roles']);

        if (! empty($validated['branch_id'])) {
            $branch = Branch::findOrFail($validated['branch_id']);
            $this->authorizeBranch($branch);
        } elseif (! empty($validated['company_id'])) {
            $this->authorizeCompanyId((int) $validated['company_id']);
        }

        $payload = [
            'name' => $validated['name'],
            'employee_code' => $validated['employee_code'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $user->roles()->sync($validated['roles']);
        if ($request->has('kitchens')) {
            $user->kitchens()->sync($this->selectedRolesIncludeKitchenStaff($validated['roles'])
                ? ($validated['kitchens'] ?? [])
                : []);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if((int) $user->id === (int) $this->actor()->id, 403, 'You cannot delete your own account.');

        $this->authorizeUserAccess($user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    protected function authorizeUserAccess(User $user): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        // Managers and non-admins cannot manage admin users
        abort_if($user->isAdmin(), 403, 'You are not authorized to manage administrator accounts.');

        // Must belong to the same company
        abort_unless((int) $user->company_id === (int) $this->actor()->company_id, 403, 'Unauthorized company access.');

        // If locked to a branch, must belong to the exact branch
        if ($this->locksBranch()) {
            abort_unless((int) $user->branch_id === (int) $this->actor()->branch_id, 403, 'Unauthorized branch access.');
        }
    }

    protected function authorizeUserRoleAssignment(array $roleIds): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        // Non-admins cannot assign admin or super-admin roles
        $hasAdminRole = Role::whereIn('id', $roleIds)
            ->whereIn('slug', ['admin', 'super-admin'])
            ->exists();

        abort_if($hasAdminRole, 403, 'You are not authorized to assign administrator roles.');
    }

    protected function assignableRoles(): Collection
    {
        $query = Role::query()->where('is_active', true)->orderBy('name');

        if (! $this->isSuperAdmin()) {
            $query->whereNotIn('slug', ['admin', 'super-admin']);
        }

        return $query->get();
    }

    protected function validateUserData(Request $request, ?User $user = null): array
    {
        $isCreate = $user === null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                $isCreate ? 'unique:users,employee_code' : Rule::unique('users', 'employee_code')->ignore($user?->id),
            ],
            'designation' => ['nullable', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $isCreate ? 'unique:users,email' : Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'company_id' => [$this->locksCompany() ? 'required' : 'nullable', 'exists:companies,id'],
            'branch_id' => [$this->locksBranch() ? 'required' : 'nullable', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
            'kitchens' => ['nullable', 'array'],
            'kitchens.*' => [
                'integer',
                Rule::exists('kitchens', 'id')->where(fn ($query) => $query
                    ->where('company_id', $request->input('company_id'))
                    ->where('branch_id', $request->input('branch_id'))
                    ->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function kitchenAssignmentChoices(?int $companyId, ?int $branchId): Collection
    {
        if (! $companyId || ! $branchId) {
            return collect();
        }

        $this->authorizeCompanyId($companyId);
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorizeBranch($branch);
        abort_unless((int) $branch->company_id === $companyId, 403);

        return Kitchen::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function selectedRolesIncludeKitchenStaff(array $roleIds): bool
    {
        return Role::query()
            ->whereIn('id', $roleIds)
            ->whereIn('slug', ['kitchen-staff', 'kitchen-manager'])
            ->exists();
    }
}

