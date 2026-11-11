<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmPushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private static bool $loggedUnconfigured = false;

    public function send(MobileDeviceToken $device, array $notification, array $data): void
    {
        $projectId = (string) config('services.fcm.project_id');
        $credentialsPath = (string) config('services.fcm.service_account_path');
        if ($projectId === '' || $credentialsPath === '') {
            $this->logUnconfiguredOnce();
            return;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($this->accessToken($projectId, $credentialsPath))
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $device->token,
                    'notification' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'tag' => $data['notification_id'] ?? 'kot-update',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) return;

        $payload = $response->json();
        $fcmErrorCode = collect(data_get($payload, 'error.details', []))
            ->first(fn ($detail) => is_array($detail)
                && str_ends_with((string) ($detail['@type'] ?? ''), 'FcmError'))['errorCode'] ?? null;
        if ($fcmErrorCode === 'UNREGISTERED') {
            $device->delete();
            return;
        }

        if (in_array($response->status(), [408, 429], true) || $response->serverError()) {
            throw new RuntimeException('FCM temporarily rejected a push notification.');
        }

        Log::warning('FCM rejected a mobile notification.', [
            'status' => $response->status(),
            'error_code' => data_get($payload, 'error.status'),
        ]);
    }

    private function accessToken(string $projectId, string $credentialsPath): string
    {
        $cacheKey = 'fcm.oauth.'.hash('sha256', $projectId.'|'.$credentialsPath);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $path = str_starts_with($credentialsPath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath)
                ? $credentialsPath
                : base_path($credentialsPath);
        if (! is_file($path)) {
            throw new RuntimeException('The configured FCM service-account file is unavailable.');
        }
        $credentials = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;
        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        if (! is_string($clientEmail) || ! is_string($privateKey) || ! is_string($tokenUri)) {
            throw new RuntimeException('The configured FCM service-account file is incomplete.');
        }
        if (isset($credentials['project_id']) && $credentials['project_id'] !== $projectId) {
            throw new RuntimeException('FCM project ID does not match the service-account project.');
        }

        $issuedAt = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $unsignedAssertion = $header.'.'.$claim;
        $signature = '';
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || ! openssl_sign($unsignedAssertion, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The FCM service-account private key could not sign an access assertion.');
        }

        $response = Http::asForm()->timeout(15)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsignedAssertion.'.'.$this->base64UrlEncode($signature),
        ]);
        $accessToken = $response->json('access_token');
        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('FCM OAuth authorization failed.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 60)));
        return $accessToken;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function logUnconfiguredOnce(): void
    {
        if (self::$loggedUnconfigured) return;
        self::$loggedUnconfigured = true;
        Log::warning('FCM push is disabled because FCM_PROJECT_ID or FCM_SERVICE_ACCOUNT_PATH is missing.');
    }
}
