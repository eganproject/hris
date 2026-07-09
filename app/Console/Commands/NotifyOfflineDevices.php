<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Support\ApprovalNotifier;
use Illuminate\Console\Command;

/**
 * Alert HR when an active attendance machine has stopped contacting the server for
 * longer than the alert window. Fires once per outage: the device's
 * offline_notified_at guards against repeats and is cleared on the next check-in.
 */
class NotifyOfflineDevices extends Command
{
    protected $signature = 'devices:notify-offline';

    protected $description = 'Kirim notifikasi ke HR untuk mesin absensi yang offline (berhenti mengirim data).';

    public function handle(ApprovalNotifier $notifier): int
    {
        $cutoff = now()->subMinutes(Device::OFFLINE_ALERT_MINUTES);
        $sent = 0;

        Device::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $cutoff)
            ->whereNull('offline_notified_at')
            ->get()
            ->each(function (Device $device) use ($notifier, &$sent) {
                $minutesOffline = (int) $device->last_seen_at->diffInMinutes(now());

                $notifier->deviceOffline($device, $minutesOffline);
                $device->forceFill(['offline_notified_at' => now()])->save();
                $sent++;
            });

        $this->info("Notifikasi mesin offline dikirim: {$sent}.");

        return self::SUCCESS;
    }
}
