<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class MobilePushTokenController extends Controller
{
    public function storeWaiter(Request $request): JsonResponse
    {
        abort_unless($request->user()->isWaiter(), 403);
        return $this->store($request, 'waiter');
    }

    public function destroyWaiter(Request $request): JsonResponse
    {
        abort_unless($request->user()->isWaiter(), 403);
        return $this->destroy($request, 'waiter');
    }

    public function storeKitchen(Request $request): JsonResponse
    {
        abort_unless($request->user()->isKitchenStaff(), 403);
        return $this->store($request, 'kitchen');
    }

    public function destroyKitchen(Request $request): JsonResponse
    {
        abort_unless($request->user()->isKitchenStaff(), 403);
        return $this->destroy($request, 'kitchen');
    }

    private function store(Request $request, string $client): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ]);
        $accessToken = $request->user()->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 403);

        $tokenHash = hash('sha256', $validated['token']);
        DB::transaction(function () use ($request, $accessToken, $client, $validated, $tokenHash): void {
            MobileDeviceToken::query()
                ->where('personal_access_token_id', $accessToken->id)
                ->where('client', $client)
                ->where('token_hash', '<>', $tokenHash)
                ->delete();

            MobileDeviceToken::query()->updateOrCreate(
                ['token_hash' => $tokenHash],
                [
                    'user_id' => $request->user()->id,
                    'personal_access_token_id' => $accessToken->id,
                    'client' => $client,
                    'token' => $validated['token'],
                    'platform' => $validated['platform'],
                    'last_seen_at' => now(),
                ],
            );
        });

        return response()->json(['message' => 'Push notifications registered.']);
    }

    private function destroy(Request $request, string $client): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);
        $accessToken = $request->user()->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 403);

        MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('personal_access_token_id', $accessToken->id)
            ->where('client', $client)
            ->where('token_hash', hash('sha256', $validated['token']))
            ->delete();

        return response()->json(['message' => 'Push notifications unregistered.']);
    }
}
