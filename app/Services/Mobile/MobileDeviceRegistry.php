<?php

namespace App\Services\Mobile;

use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Catatan HP yang dipakai karyawan untuk aplikasi.
 *
 * Sejak 2026-11-26 tidak ada lagi kunci satu HP: aplikasi HP tidak dipakai
 * untuk absen (absen lewat tablet kantor / mesin), jadi setiap HP yang dipakai
 * login langsung aktif tanpa persetujuan HR. HR masih bisa mencabut HP
 * (keluar paksa); login ulang dari HP itu mengaktifkannya lagi.
 */
class MobileDeviceRegistry
{
    /**
     * @param  array{device_id: string, platform: string, model?: ?string, os_version?: ?string, app_version?: ?string}  $info
     */
    public function register(User $user, Employee $employee, array $info): MobileDevice
    {
        return DB::transaction(function () use ($user, $employee, $info) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $device = MobileDevice::query()->firstOrNew([
                'employee_id' => $employee->id,
                'device_uuid' => $info['device_id'],
            ]);

            $device->fill([
                'user_id' => $user->id,
                'platform' => $info['platform'],
                'model' => $info['model'] ?? $device->model,
                'os_version' => $info['os_version'] ?? $device->os_version,
                'app_version' => $info['app_version'] ?? $device->app_version,
            ]);

            if ($device->status !== MobileDevice::STATUS_ACTIVE) {
                $device->status = MobileDevice::STATUS_ACTIVE;
                $device->decided_by = null;
                $device->decided_at = now();
                $device->decision_note = 'Aktif otomatis saat login.';
            }

            $device->save();

            return $device;
        });
    }

    public function approve(MobileDevice $device, User $actor, ?string $note = null): void
    {
        $device->forceFill([
            'status' => MobileDevice::STATUS_ACTIVE,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
    }

    public function reject(MobileDevice $device, User $actor, ?string $note = null): void
    {
        $this->deleteTokens($device);

        $device->forceFill([
            'status' => MobileDevice::STATUS_REJECTED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
    }

    public function revoke(MobileDevice $device, User $actor, ?string $note = null): void
    {
        $this->deleteTokens($device);

        $device->forceFill([
            'status' => MobileDevice::STATUS_REVOKED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
    }

    public function deleteTokens(MobileDevice $device): void
    {
        PersonalAccessToken::query()->where('name', $device->tokenName())->delete();
    }
}
