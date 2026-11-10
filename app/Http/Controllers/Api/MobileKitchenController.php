<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\KotTicket;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileKitchenController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (
            ! $user
            || ! Hash::check($validated['password'], $user->password)
            || ! $this->canUseKitchenApp($user)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect or unavailable.'],
            ]);
        }

        $expiresAt = now()->addDays(7);
        $token = $user->createToken($validated['device_name'], ['kitchen:mobile'], $expiresAt);

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
        return response()->json(['data' => ['user' => $this->userPayload($request->user())]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function kitchens(Request $request): JsonResponse
    {
        $user = $request->user();
        $kitchens = $user->kitchens()
            ->where('kitchens.company_id', $user->company_id)
            ->where('kitchens.branch_id', $user->branch_id)
            ->where('kitchens.is_active', true)
            ->orderBy('kitchens.name')
            ->get(['kitchens.id', 'kitchens.name', 'kitchens.code'])
            ->map(fn (Kitchen $kitchen) => [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'code' => $kitchen->code,
            ]);

        return response()->json([
            'data' => [
                'branch' => [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ],
                'kitchens' => $kitchens,
            ],
        ]);
    }

    public function tickets(Request $request, Kitchen $kitchen): JsonResponse
    {
        $this->authorizeKitchen($request->user(), $kitchen);

        $tickets = KotTicket::query()
            ->where('company_id', $request->user()->company_id)
            ->where('branch_id', $request->user()->branch_id)
            ->where('kitchen_id', $kitchen->id)
            ->where(function ($query) {
                $query->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
                    ->orWhere(fn ($cancelled) => $cancelled
                        ->where('ticket_status', 'cancelled')
                        ->where('updated_at', '>=', now()->subMinutes(15)));
            })
            ->with([
                'order:id,order_number,table_id,created_at,special_instructions',
                'order.table:id,name',
                'items:id,kot_ticket_id,menu_item_id,quantity,special_instructions,item_status',
                'items.menuItem:id,name,menu_type_id',
                'items.menuItem.menuType:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => [
                'kitchen' => [
                    'id' => $kitchen->id,
                    'name' => $kitchen->name,
                    'code' => $kitchen->code,
                ],
                'tickets' => $tickets,
                'refreshed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function updateTicketStatus(Request $request, KotTicket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            (int) $ticket->company_id === (int) $user->company_id
                && (int) $ticket->branch_id === (int) $user->branch_id,
            404
        );

        $kitchen = Kitchen::query()->findOrFail($ticket->kitchen_id);
        $this->authorizeKitchen($user, $kitchen);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready'],
        ]);

        $current = $ticket->ticket_status;
        $next = $validated['status'];
        $allowedTransitions = [
            'new' => ['accepted'],
            'accepted' => ['preparing'],
            'preparing' => ['ready'],
        ];

        if ($current !== $next && ! in_array($next, $allowedTransitions[$current] ?? [], true)) {
            return response()->json([
                'message' => 'This ticket has changed. Refresh the kitchen board before updating it.',
            ], 409);
        }

        $updated = $current === $next
            ? $ticket->fresh(['order.table', 'items.menuItem.menuType'])
            : $this->orderService->updateKotTicketStatus($ticket, $next);

        return response()->json([
            'message' => 'KOT ticket updated.',
            'data' => ['ticket' => $updated],
        ]);
    }

    private function authorizeKitchen(User $user, Kitchen $kitchen): void
    {
        abort_unless(
            $kitchen->is_active
                && (int) $kitchen->company_id === (int) $user->company_id
                && (int) $kitchen->branch_id === (int) $user->branch_id
                && $user->kitchens()->whereKey($kitchen->id)->exists(),
            404
        );
    }

    private function canUseKitchenApp(User $user): bool
    {
        return $user->canAuthenticate()
            && $user->company_id !== null
            && $user->branch_id !== null
            && $user->isKitchenStaff()
            && $user->hasPermission('kot.view')
            && $user->hasPermission('kot.edit');
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['company:id,name,code', 'branch:id,company_id,name,code']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'code' => $user->company->code,
            ],
            'branch' => [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'code' => $user->branch->code,
            ],
        ];
    }
}
