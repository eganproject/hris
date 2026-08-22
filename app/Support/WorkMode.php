<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Services\AttendanceResolver;

/**
 * Bagaimana satu hari kerja dijalani menurut jadwal & pengajuan yang disetujui:
 * dari kantor, dari rumah, di luar kota, atau memang tidak bekerja.
 *
 * Urutan prioritasnya mengikuti AttendanceResolver supaya yang tampil di jadwal
 * tidak pernah bertolak belakang dengan status absensi yang nanti dihitung. Kelas
 * ini dipakai bersama oleh grid roster, jadwal per karyawan, jadwal karyawan
 * sendiri, dan papan absensi harian — satu tempat, jadi keempatnya tidak bisa
 * menampilkan hal yang berbeda untuk hari yang sama.
 */
final class WorkMode
{
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $short,
        public readonly bool $isWorking,
    ) {}

    public static function for(?EmployeeSchedule $schedule, ?LeaveRequest $leave = null): self
    {
        $shiftCode = $schedule?->shift?->code ?? '?';
        $leaveStatus = $leave?->leaveType?->attendance_status;

        // 1. WFH & dinas luar yang disetujui tetap BEKERJA — tidak boleh disamakan
        //    dengan cuti. Inilah yang membedakan kelas ini dari perlakuan lama, yang
        //    mewarnai semua pengajuan disetujui sebagai "tidak masuk".
        if ($leaveStatus !== null && in_array($leaveStatus, AttendanceResolver::REMOTE_STATUSES, true)) {
            return $leaveStatus === AttendanceStatus::Wfh
                ? new self('wfh', 'WFH', '🏠', true)
                : new self('trip', 'Dinas Luar', 'DL', true);
        }

        // 2. Cuti/izin/sakit: orangnya memang tidak bekerja hari itu.
        if ($leave) {
            return new self('leave', $leave->leaveType?->name ?? 'Cuti', $leave->leaveType?->code ?? 'C', false);
        }

        if (! $schedule) {
            return new self('none', 'Belum dijadwalkan', '·', false);
        }

        if ($schedule->is_day_off) {
            return new self('off', 'Libur', '—', false);
        }

        // 3. WFH dari roster (pola, override harian, atau impor).
        return $schedule->is_wfh
            ? new self('wfh', 'WFH', '🏠', true)
            : new self('office', $schedule->shift?->name ?? 'Shift', $shiftCode, true);
    }

    public function isRemote(): bool
    {
        return in_array($this->key, ['wfh', 'trip'], true);
    }

    /**
     * Warna chip/sel. Satu peta untuk semua permukaan, supaya biru-nya WFH tidak
     * berubah arti dari satu halaman ke halaman lain.
     */
    public function chipClasses(): string
    {
        return match ($this->key) {
            'wfh' => 'bg-indigo-100 text-indigo-700',
            'trip' => 'bg-blue-100 text-blue-700',
            'leave' => 'bg-amber-100 text-amber-800',
            'office' => 'bg-primary/10 text-primary',
            'off' => 'bg-gray-50 text-gray-400',
            default => 'text-gray-300',
        };
    }

    /** Keterangan lengkap untuk tooltip: mode kerja plus shift yang berlaku. */
    public function describe(?EmployeeSchedule $schedule): string
    {
        if (! $this->isRemote() || ! $schedule?->shift) {
            return $this->label;
        }

        return $this->label.' — jam '.$schedule->shift->name;
    }
}
