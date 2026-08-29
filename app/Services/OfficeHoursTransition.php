<?php

namespace App\Services;

use App\Enums\ScheduleSource;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Membereskan sisa penjadwalan ketika karyawan dipindahkan ke "ikut jam kantor".
 *
 * Menyalakan flag-nya saja tidak cukup. Jadwal jam kantor diturunkan dari pola saat
 * dibaca, dan baris jadwal nyata SELALU menang atas turunan itu (lihat
 * DefaultOfficeSchedule::fill). Penugasan pola dan baris hasil generate yang
 * tertinggal karenanya membuat flag-nya seolah tidak berpengaruh: orangnya berlabel
 * "Jam kantor" di roster, tapi sel-selnya — dan absensinya — masih memakai pola lama
 * sampai jadwal itu habis sendiri.
 *
 * Dua batas yang tidak boleh dilanggar:
 *   - hanya menyentuh hari ini ke depan, supaya riwayat absensi yang sudah
 *     terkunci tidak berubah surut;
 *   - hanya baris 'generated', supaya override manual tetap utuh — sama seperti
 *     ScheduleGenerator yang juga menolak menimpanya.
 */
class OfficeHoursTransition
{
    public function __construct(private readonly ScheduleAttendanceSynchronizer $attendanceSynchronizer) {}

    /**
     * @param  iterable<Employee>  $employees
     * @return array{assignments: int, days: int} jumlah penugasan yang dihentikan dan hari jadwal yang dibersihkan
     */
    public function apply(iterable $employees): array
    {
        $employees = Collection::make($employees);

        if ($employees->isEmpty()) {
            return ['assignments' => 0, 'days' => 0];
        }

        $today = Carbon::today();

        return [
            'assignments' => $this->stopAssignments($employees->pluck('id')->all(), $today),
            'days' => $this->clearGeneratedSchedules($employees, $today),
        ];
    }

    /**
     * Hentikan penugasan pola yang masih akan berlaku, agar tombol Generate tidak
     * bisa membangkitkannya lagi nanti.
     *
     * @param  list<int>  $employeeIds
     */
    private function stopAssignments(array $employeeIds, Carbon $today): int
    {
        // Penugasan yang belum sempat mulai tidak bisa "ditutup kemarin" — tanggal
        // akhirnya akan mendahului tanggal mulainya — jadi dibuang saja.
        $dropped = ScheduleAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '>=', $today)
            ->delete();

        // Yang sudah berjalan ditutup, bukan dihapus: jejak "dulu orang ini memakai
        // pola X" tetap terbaca, dan jadwal masa lalunya tetap punya induk.
        $closed = ScheduleAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '<', $today)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->update(['end_date' => $today->copy()->subDay()->toDateString()]);

        return $dropped + $closed;
    }

    /**
     * Buang baris jadwal hasil generate mulai hari ini ke depan, supaya pola jam
     * kantor langsung berlaku dan bukan baru setelah jadwal lama habis.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function clearGeneratedSchedules(Collection $employees, Carbon $today): int
    {
        $days = 0;

        foreach ($employees as $employee) {
            $rows = fn () => EmployeeSchedule::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $today)
                ->where('source', ScheduleSource::Generated->value);

            $last = $rows()->max('work_date');

            if (! $last) {
                continue;
            }

            $days += $rows()->delete();

            // Absensi yang terlanjur dihitung dari jadwal lama dihitung ulang. Tanpa
            // baris jadwal, resolver jatuh ke pola jam kantor — yang justru kita mau.
            $this->attendanceSynchronizer->forRange($employee, $today, Carbon::parse($last));
        }

        return $days;
    }
}
