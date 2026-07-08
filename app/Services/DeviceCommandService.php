<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\EmployeeDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Queues and delivers ZKTeco iclock commands to a device. Commands wait in the
 * queue until the device's next getrequest poll, then are delivered as
 * "C:<id>:<command>" lines and acknowledged via devicecmd.
 */
class DeviceCommandService
{
    public function queue(Device $device, string $label, string $command): DeviceCommand
    {
        return $device->commands()->create([
            'label' => $label,
            'command' => $command,
            'status' => DeviceCommand::STATUS_PENDING,
            'created_by' => Auth::id(),
        ]);
    }

    public function check(Device $device): DeviceCommand
    {
        return $this->queue($device, 'Cek koneksi', 'CHECK');
    }

    public function info(Device $device): DeviceCommand
    {
        return $this->queue($device, 'Minta info perangkat', 'INFO');
    }

    public function reboot(Device $device): DeviceCommand
    {
        return $this->queue($device, 'Reboot mesin', 'REBOOT');
    }

    public function clearAttendanceLog(Device $device): DeviceCommand
    {
        return $this->queue($device, 'Hapus log absensi di mesin', 'CLEAR LOG');
    }

    /**
     * Push (create/update) a user's name for a PIN onto the device.
     */
    public function syncUser(Device $device, string $pin, string $name): DeviceCommand
    {
        $name = str($name)->replace(["\t", "\r", "\n"], ' ')->limit(24, '')->trim()->toString();

        $payload = "DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=";

        return $this->queue($device, "Sinkron user PIN {$pin} ({$name})", $payload);
    }

    /**
     * Queue a name sync for every PIN mapped to this device (device-specific + global).
     */
    public function syncAllUsers(Device $device): int
    {
        $mappings = EmployeeDevice::query()
            ->where(fn ($q) => $q->where('device_id', $device->id)->orWhereNull('device_id'))
            ->with('employee')
            ->get();

        $count = 0;

        foreach ($mappings as $mapping) {
            if (! $mapping->employee) {
                continue;
            }

            $this->syncUser($device, $mapping->machine_user_id, $mapping->employee->full_name);
            $count++;
        }

        return $count;
    }

    public function deleteUser(Device $device, string $pin): DeviceCommand
    {
        return $this->queue($device, "Hapus user PIN {$pin}", "DATA DELETE USERINFO PIN={$pin}");
    }

    /**
     * Pull the next batch of pending commands for delivery, marking them as sent.
     *
     * @return Collection<int, DeviceCommand>
     */
    public function nextBatch(Device $device, int $limit = 10): Collection
    {
        $commands = $device->commands()->pending()->orderBy('id')->limit($limit)->get();

        if ($commands->isNotEmpty()) {
            DeviceCommand::query()->whereIn('id', $commands->pluck('id'))->update([
                'status' => DeviceCommand::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }

        return $commands;
    }

    public function toWire(DeviceCommand $command): string
    {
        return "C:{$command->id}:{$command->command}";
    }

    /**
     * Record a command result reported by the device (Return=0 means success).
     */
    public function acknowledge(Device $device, int $commandId, int $returnCode): void
    {
        $device->commands()->whereKey($commandId)->update([
            'status' => $returnCode === 0 ? DeviceCommand::STATUS_DONE : DeviceCommand::STATUS_FAILED,
            'return_code' => $returnCode,
            'completed_at' => now(),
        ]);
    }
}
