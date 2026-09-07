<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Parses the raw ATTLOG payload the fingerprint machine pushes, stores each punch
 * idempotently, maps it to an employee, and triggers the daily rollup. Unmatched
 * punches (PIN not enrolled in our system yet) are kept for later assignment.
 */
class PunchIngestionService
{
    public function __construct(private readonly AttendanceRollup $rollup)
    {
    }

    /**
     * Ingest a tab-separated ATTLOG body. Each line: PIN, DateTime, State, Verify, ...
     * Returns the number of newly-stored (non-duplicate) punches.
     */
    public function ingestAttlog(Device $device, string $body): int
    {
        $affected = collect(); // [ "employeeId|Y-m-d" => [employee, date] ]
        $new = 0;

        foreach (preg_split('/\r\n|\r|\n/', trim($body)) as $line) {
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseLine($device, $line);

            if (! $parsed) {
                continue;
            }

            ['pin' => $pin, 'punched_at' => $punchedAt, 'state' => $state, 'verify' => $verify] = $parsed;

            $employee = $this->resolveEmployee($device, $pin);
            $dedup = $this->dedupHash($device, $parsed);

            $punch = AttendancePunch::query()->firstOrNew(['dedup_hash' => $dedup]);

            if ($punch->exists) {
                continue; // idempotent: already received this exact punch
            }

            $punch->fill([
                'device_id' => $device->id,
                'employee_id' => $employee?->id,
                'machine_user_id' => $pin,
                'punched_at' => $punchedAt,
                'state' => $state,
                'verify_mode' => $verify,
                'status' => $employee ? AttendancePunch::STATUS_MATCHED : AttendancePunch::STATUS_UNMATCHED,
                'raw' => $line,
            ])->save();

            $new++;

            if ($employee) {
                // A punch may belong to its own day or the previous night shift.
                foreach ([$punchedAt->copy()->subDay(), $punchedAt->copy()] as $date) {
                    $key = $employee->id.'|'.$date->toDateString();
                    $affected[$key] = [$employee, $date->copy()];
                }
            }
        }

        $this->rebuildAll($affected);

        return $new;
    }

    /**
     * Enroll a PIN → employee mapping and back-fill any punches already received
     * under that PIN (device-specific or global), then re-roll their days.
     */
    public function assignPin(Employee $employee, ?Device $device, string $pin): EmployeeDevice
    {
        $mapping = EmployeeDevice::query()->updateOrCreate(
            ['device_id' => $device?->id, 'machine_user_id' => $pin],
            ['employee_id' => $employee->id],
        );

        $punches = AttendancePunch::query()
            ->where('machine_user_id', $pin)
            ->where('status', AttendancePunch::STATUS_UNMATCHED)
            ->when($device, fn ($query) => $query->where('device_id', $device->id))
            ->get();

        $affected = collect();

        foreach ($punches as $punch) {
            $punch->forceFill(['employee_id' => $employee->id, 'status' => AttendancePunch::STATUS_MATCHED])->save();

            foreach ([$punch->punched_at->copy()->subDay(), $punch->punched_at->copy()] as $date) {
                $affected[$employee->id.'|'.$date->toDateString()] = [$employee, $date->copy()];
            }
        }

        $this->rebuildAll($affected);

        return $mapping;
    }

    /**
     * @param  Collection<string, array{0: Employee, 1: Carbon}>  $affected
     */
    private function rebuildAll(Collection $affected): void
    {
        foreach ($affected as [$employee, $date]) {
            $this->rollup->rebuild($employee, $date);
        }
    }

    private function resolveEmployee(Device $device, string $pin): ?Employee
    {
        $mapping = EmployeeDevice::query()
            ->where('machine_user_id', $pin)
            ->where(fn ($query) => $query->where('device_id', $device->id)->orWhereNull('device_id'))
            ->orderByRaw('device_id IS NULL') // device-specific mapping wins over global
            ->with('employee')
            ->first();

        return $mapping?->employee;
    }

    /**
     * Uraikan satu baris ATTLOG. Dipakai bersama oleh penerimaan kiriman dan halaman
     * detail log — satu-satunya cara memastikan yang ditampilkan di layar dibaca
     * dengan aturan yang sama seperti saat datanya masuk.
     *
     * @return array{pin: string, punched_at: Carbon, state: int, verify: int}|null
     */
    public function parseLine(Device $device, string $line): ?array
    {
        $parts = preg_split('/\t+/', trim($line));

        if (count($parts) < 2) {
            return null;
        }

        $pin = trim($parts[0]);
        $punchedAt = $this->parseTime($device, trim($parts[1]));

        if (! $pin || ! $punchedAt) {
            return null;
        }

        return [
            'pin' => $pin,
            'punched_at' => $punchedAt,
            'state' => isset($parts[2]) ? (int) $parts[2] : 0,
            'verify' => isset($parts[3]) ? (int) $parts[3] : 0,
        ];
    }

    /**
     * Sidik jari sebuah punch: mesin, PIN, waktu, dan state-nya. Rumusnya diberi nama
     * supaya halaman detail bisa menanyakan "baris ini menjadi punch yang mana" dengan
     * kunci yang sama persis, bukan dengan salinan rumus yang bisa bergeser.
     *
     * @param  array{pin: string, punched_at: Carbon, state: int, verify: int}  $parsed
     */
    public function dedupHash(Device $device, array $parsed): string
    {
        return sha1("{$device->id}|{$parsed['pin']}|{$parsed['punched_at']->toIso8601String()}|{$parsed['state']}");
    }

    private function parseTime(Device $device, string $time): ?Carbon
    {
        try {
            return Carbon::parse($time, $device->timezone ?: config('app.timezone'))
                ->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
