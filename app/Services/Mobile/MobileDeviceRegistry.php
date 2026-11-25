<?php

namespace App\Services\Mobile;

use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pengikatan HP ke karyawan: satu HP aktif per karyawan.
 *
 * - HP pertama karyawan langsung aktif.
 * - HP lain saat sudah ada HP aktif = permintaan ganti HP, menunggu HR.
 * - HR menyetujui: HP lama dicabut (tokennya dihapus), HP baru aktif.
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

            if (! $device->exists) {
                $hasActive = $employee->mobileDevices()->active()->exists();
                $device->status = $hasActive ? MobileDevice::STATUS_PENDING : MobileDevice::STATUS_ACTIVE;
                if (! $hasActive) {
                    $device->decided_at = now();
                    $device->decision_note = 'HP pertama, aktif otomatis.';
                }
            } elseif (in_array($device->status, [MobileDevice::STATUS_REVOKED], true)) {
                // HP yang pernah dicabut dan dipakai login lagi: ajukan ulang ke HR.
                $device->status = MobileDevice::STATUS_PENDING;
                $device->decided_by = null;
                $device->decided_at = null;
                $device->decision_note = null;
            }

            $device->save();

            return $device;
        });
    }

    public function approve(MobileDevice $device, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($device, $actor, $note) {
            $others = MobileDevice::query()
                ->where('employee_id', $device->employee_id)
                ->whereKeyNot($device->id)
                ->where('status', MobileDevice::STATUS_ACTIVE)
                ->get();

            foreach ($others as $other) {
                $this->revoke($other, $actor, 'Diganti HP baru ('.$device->label.').');
            }

            // Permintaan lain yang masih menunggu otomatis gugur.
            MobileDevice::query()
                ->where('employee_id', $device->employee_id)
                ->whereKeyNot($device->id)
                ->where('status', MobileDevice::STATUS_PENDING)
                ->update([
                    'status' => MobileDevice::STATUS_REJECTED,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => 'Gugur: HP lain disetujui.',
                ]);

            $device->forceFill([
                'status' => MobileDevice::STATUS_ACTIVE,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();
        });
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
