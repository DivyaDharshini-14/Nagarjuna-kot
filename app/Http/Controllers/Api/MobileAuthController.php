<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (
            ! $user
            || ! Hash::check($validated['password'], $user->password)
            || ! $this->canUseWaiterApp($user)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect or unavailable.'],
            ]);
        }

        $expiresAt = now()->addDays(7);
        $token = $user->createToken(
            $validated['device_name'],
            ['waiter:mobile'],
            $expiresAt
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $this->userPayload($request->user()),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function canUseWaiterApp(User $user): bool
    {
        $hasOrderEntryAccess = $user->isWaiter()
            && $user->hasPermission('floor.view')
            && $user->hasPermission('table.view')
            && $user->hasPermission('category.view')
            && $user->hasPermission('menu-type.view')
            && $user->hasPermission('menu-item.view')
            && $user->hasPermission('order.view')
            && $user->hasPermission('order.add');
        $hasCancellationApprovalAccess = $user->hasPermission('order.view')
            && $user->hasPermission('order.approve');

        return $user->canAuthenticate()
            && $user->company_id !== null
            && $user->branch_id !== null
            && ($hasOrderEntryAccess || $hasCancellationApprovalAccess);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing([
            'company:id,name,code,gst_number',
            'branch:id,company_id,name,code',
        ]);

        $roles = $user->roles()
            ->where('roles.is_active', true)
            ->orderBy('roles.name')
            ->get(['roles.name', 'roles.slug'])
            ->map(fn ($role) => [
                'name' => $role->name,
                'slug' => $role->slug,
            ])
            ->values();

        $permissions = $user->roles()
            ->where('roles.is_active', true)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'employee_code' => $user->employee_code,
            'designation' => $user->designation,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $roles,
            'permissions' => $permissions,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'code' => $user->company->code,
                'gst_number' => $user->company->gst_number,
            ] : null,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'code' => $user->branch->code,
            ] : null,
        ];
    }
}
