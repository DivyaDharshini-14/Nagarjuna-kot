<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    protected array $protectedRoles = ['admin', 'super-admin'];

    public function index(Request $request): View
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = trim($request->search);
            $roles->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->status === 'active') {
            $roles->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $roles->where('is_active', false);
        }

        $roles = $roles->paginate(10)->withQueryString();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissionsByModule = Permission::all()->groupBy('module');
        $rolePermissions = [];

        return view('roles.create', compact('permissionsByModule', 'rolePermissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->filled('slug') && $request->filled('name')) {
            $request->merge(['slug' => Str::slug($request->name)]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $permissionsByModule = Permission::all()->groupBy('module');
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        $isProtected = in_array($role->slug, $this->protectedRoles, true);

        return view('roles.edit', compact('role', 'permissionsByModule', 'rolePermissions', 'isProtected'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (! $request->filled('slug') && $request->filled('name')) {
            $request->merge(['slug' => Str::slug($request->name)]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => in_array($role->slug, $this->protectedRoles, true) ? $role->slug : $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => in_array($role->slug, $this->protectedRoles, true) ? true : $request->boolean('is_active', true),
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Role and permissions updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if(in_array($role->slug, $this->protectedRoles, true), 403, 'Core system administrator roles cannot be deleted.');

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Cannot delete role assigned to active users. Please reassign users first.']);
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }
}

