<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use App\Models\User;

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
        if (! $this->fcm->configured()) {
            return 0;
        }

        $devices = MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('status', MobileDevice::STATUS_ACTIVE)
            ->whereNotNull('push_token')
            ->get();

        $sent = 0;

        foreach ($devices as $device) {
            $result = $this->fcm->send($device->push_token, $title, $body, $data);

            if ($result === FcmClient::SENT) {
                $sent++;
            } elseif ($result === FcmClient::INVALID_TOKEN) {
                $device->forceFill(['push_token' => null, 'push_token_updated_at' => now()])->save();
            }
        }

        return $sent;
    }
}
