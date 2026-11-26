<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\RemoteAttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Absen mandiri dengan selfie untuk hari kerja jarak jauh (WFH terjadwal, atau
 * WFH/dinas luar yang disetujui). Dipakai bersama oleh menu web "Absensi Saya" dan
 * aplikasi HP, supaya keduanya menjawab "boleh absen sendiri hari ini?" dengan cara
 * yang sama dan menulis ke baris absensi yang sama.
 *
 * Lokasi tidak dibatasi (tanpa geofence): koordinatnya hanya disimpan sebagai titik
 * absen, dan tampil di peta absen mandiri.
 */
class RemoteSelfAttendance
{
    public function __construct(
        private readonly AttendanceResolver $resolver,
        private readonly AttendanceRollup $rollup,
    ) {}

    /**
     * Tanggal kerja yang sedang dijalani. Untuk shift lintas tengah malam ini adalah
     * tanggal kemarin sampai shiftnya berakhir.
     */
    public function workDate(Employee $employee): Carbon
    {
        return $this->rollup->workDateFor($employee, now());
    }

    /**
     * WFH dari roster, atau WFH/dinas luar dari pengajuan yang disetujui. Null berarti
     * hari kantor biasa — absen mandiri tidak berlaku.
     */
    public function statusOn(Employee $employee, Carbon $workDate): ?AttendanceStatus
    {
        $date = $workDate->toDateString();

        $scheduled = $employee->schedules()
            ->whereDate('work_date', $date)
            ->where('is_wfh', true)
            ->exists();

        if ($scheduled) {
            return AttendanceStatus::Wfh;
        }

        $remoteValues = array_map(fn (AttendanceStatus $s) => $s->value, AttendanceResolver::REMOTE_STATUSES);

        $leave = $employee->leaveRequests()
            ->approvedOn($date)
            ->whereHas('leaveType', fn ($query) => $query->whereIn('attendance_status', $remoteValues))
            ->with('leaveType')
            ->first();

        return $leave?->leaveType?->attendance_status;
    }

    public function shiftLabel(Carbon $workDate): string
    {
        return $workDate->isSameDay(now()) ? 'hari ini' : 'shift '.$workDate->translatedFormat('d M Y');
    }

    /**
     * @return array{attendance: Attendance, status: AttendanceStatus, time: string}
     *
     * @throws RemoteAttendanceException
     */
    public function clockIn(Employee $employee, UploadedFile $photo, float $latitude, float $longitude, ?float $accuracy): array
    {
        $workDate = $this->workDate($employee);
        $status = $this->requireRemote($employee, $workDate);
        $now = now();
        $existing = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        if ($existing?->clock_in) {
            throw new RemoteAttendanceException('Anda sudah absen masuk untuk '.$this->shiftLabel($workDate).' pukul '.$existing->clock_in->format('H:i').'.');
        }

        // Jam diambil dari server. Untuk shift malam, jam pulang yang lebih awal dari
        // jam masuk digulirkan ke hari berikutnya oleh AttendanceResolver.
        $attendance = $this->resolver->resolve($employee, $workDate, $now->format('H:i'), $existing?->clock_out?->format('H:i'), $existing?->note);
        $this->attachProof($attendance, 'in', $photo, $latitude, $longitude, $accuracy);

        return ['attendance' => $attendance, 'status' => $status, 'time' => $now->format('H:i')];
    }

    /**
     * @return array{attendance: Attendance, status: AttendanceStatus, time: string}
     *
     * @throws RemoteAttendanceException
     */
    public function clockOut(Employee $employee, UploadedFile $photo, float $latitude, float $longitude, ?float $accuracy): array
    {
        $workDate = $this->workDate($employee);
        $status = $this->requireRemote($employee, $workDate);
        $now = now();
        $existing = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        if (! $existing?->clock_in) {
            throw new RemoteAttendanceException('Absen masuk dulu sebelum absen pulang.');
        }

        if ($existing->clock_out) {
            throw new RemoteAttendanceException('Anda sudah absen pulang untuk '.$this->shiftLabel($workDate).' pukul '.$existing->clock_out->format('H:i').'.');
        }

        $attendance = $this->resolver->resolve($employee, $workDate, $existing->clock_in->format('H:i'), $now->format('H:i'), $existing->note);
        $this->attachProof($attendance, 'out', $photo, $latitude, $longitude, $accuracy);

        return ['attendance' => $attendance, 'status' => $status, 'time' => $now->format('H:i')];
    }

    private function requireRemote(Employee $employee, Carbon $workDate): AttendanceStatus
    {
        return $this->statusOn($employee, $workDate)
            ?? throw new RemoteAttendanceException('Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');
    }

    /**
     * Selfie + koordinat disimpan di baris absensi yang baru di-resolve. Kolom ini
     * tidak dihitung ulang resolver, jadi aman saat HR memproses ulang hari itu.
     */
    private function attachProof(Attendance $attendance, string $side, UploadedFile $photo, float $latitude, float $longitude, ?float $accuracy): void
    {
        $path = $photo->store('attendance/selfies/'.$attendance->work_date->format('Y/m'), Attendance::SELFIE_DISK);
        $old = $attendance->{"clock_{$side}_photo_path"};

        $attendance->forceFill([
            "clock_{$side}_photo_path" => $path,
            "clock_{$side}_latitude" => $latitude,
            "clock_{$side}_longitude" => $longitude,
            "clock_{$side}_accuracy_m" => $accuracy !== null ? (int) round($accuracy) : null,
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk(Attendance::SELFIE_DISK)->delete($old);
        }
    }
}
