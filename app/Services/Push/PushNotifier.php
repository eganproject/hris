<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mengirim notifikasi ke semua HP tempat seorang pengguna login di aplikasi.
 * Token yang ditolak Firebase (aplikasi dihapus, dll.) langsung dibersihkan.
 */
class PushNotifier
{
    public function __construct(private readonly FcmClient $fcm) {}

    /**
     * @param  array<string, scalar|null>  $data
     * @return int jumlah HP yang berhasil dikirimi
     */
    public function toUser(User $user, string $title, string $body, array $data = []): int
    {
        return collect($this->sendDetailed($user, $title, $body, $data))
            ->where('result', FcmClient::SENT)
            ->count();
    }

    /**
     * Seperti toUser(), tapi melaporkan hasil per HP (untuk uji kirim).
     *
     * @param  array<string, scalar|null>  $data
     * @return list<array{device: MobileDevice, result: string, error: ?string}>
     */
    public function sendDetailed(User $user, string $title, string $body, array $data = []): array
    {
        if (! $this->fcm->configured()) {
            return [];
        }

        $results = [];

        foreach (self::devicesFor($user) as $device) {
            $result = $this->fcm->send($device->push_token, $title, $body, $data);

            // Dicatat per HP supaya HR bisa melihat di Pengaturan HP mana yang
            // tidak menerima notifikasi dan kenapa.
            $device->forceFill([
                'last_push_at' => now(),
                'last_push_result' => $result,
                'last_push_error' => $result === FcmClient::SENT ? null : mb_substr((string) $this->fcm->lastError, 0, 255),
            ]);

            if ($result === FcmClient::INVALID_TOKEN) {
                $device->forceFill(['push_token' => null, 'push_token_updated_at' => now()]);
            }

            $device->saveQuietly();

            $results[] = ['device' => $device, 'result' => $result, 'error' => $this->fcm->lastError];
        }

        return $results;
    }

    /**
     * @return Collection<int, MobileDevice>
     */
    public static function devicesFor(User $user)
    {
        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('status', MobileDevice::STATUS_ACTIVE)
            ->whereNotNull('push_token')
            ->get();
    }
}
