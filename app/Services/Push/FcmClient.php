<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim Firebase Cloud Messaging (HTTP v1) tanpa pustaka tambahan.
 *
 * Kunci akun layanan (file JSON dari Firebase > Project settings > Service
 * accounts) disimpan di server saja, ditunjuk lewat FIREBASE_CREDENTIALS di .env.
 * File itu TIDAK pernah masuk repo.
 */
class FcmClient
{
    public const SENT = 'sent';

    /** Token sudah tidak berlaku (aplikasi dihapus / data dibersihkan). */
    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    public const NOT_CONFIGURED = 'not_configured';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null|false false = belum dibaca */
    private array|null|false $credentials = false;

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    public function projectId(): ?string
    {
        return $this->credentials()['project_id'] ?? null;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return self::NOT_CONFIGURED;
        }

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->timeout(10)
                ->post('https://fcm.googleapis.com/v1/projects/'.$credentials['project_id'].'/messages:send', [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM hanya menerima nilai string di data.
                        'data' => array_map(fn ($v) => (string) $v, array_filter($data, fn ($v) => $v !== null)),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'absensi',
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('FCM: gagal mengirim notifikasi.', ['error' => $e->getMessage()]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }

        $code = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

        if ($response->status() === 404 || in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return self::INVALID_TOKEN;
        }

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($credentials));
        }

        Log::warning('FCM: notifikasi ditolak.', ['status' => $response->status(), 'code' => $code]);

        return self::FAILED;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember($this->cacheKey($credentials), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Kunci privat Firebase tidak bisa dipakai.');
            }
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout(10)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials !== false) {
            return $this->credentials;
        }

        $path = (string) config('services.fcm.credentials');

        if ($path !== '' && ! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        $json = $path !== '' && is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        $valid = is_array($json)
            && ! empty($json['project_id'])
            && ! empty($json['client_email'])
            && ! empty($json['private_key']);

        return $this->credentials = $valid ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function cacheKey(array $credentials): string
    {
        return 'fcm.access_token.'.md5($credentials['client_email']);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
