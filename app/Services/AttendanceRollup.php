<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw device punches into the clock-in/out for an employee-day and hands
 * them to the AttendanceResolver. This is the bridge between the fingerprint feed
 * and the resolved attendance.
 */
class AttendanceRollup
{
    /** How early before the shift start a punch still belongs to the day (early arrival). */
    private const MARGIN_BEFORE_HOURS = 3;

    /** Normal late-departure buffer after the shift end. */
    private const MARGIN_AFTER_HOURS = 3;

    /**
     * How far past the shift end a clock-out can still be claimed by the day — big
     * enough to capture overtime that runs into the small hours on a normal day shift.
     * Always capped short of the next scheduled shift, so a punch is owned by one day.
     */
    private const MAX_OVERTIME_HOURS = 10;

    public function __construct(private readonly AttendanceResolver $resolver)
    {
    }

    /**
     * Rebuild the attendance for one employee-day from device punches. Returns null
     * (and leaves any existing row untouched) when there are no punches in the window,
     * so this never erases manually-entered attendance.
     *
     * Jam yang sudah ditulis MANUSIA — koreksi absensi yang disetujui, atau absen
     * mandiri berselfie — dipertahankan, dan punch yang datang sesudahnya mengisi sisi
     * yang masih kosong. Tanpa itu, seorang karyawan yang jam masuknya diperbaiki lewat
     * koreksi lalu tap pulang di mesin akan kehilangan jam masuknya: satu-satunya punch
     * hari itu direbut menjadi jam masuk, dan jam pulangnya ikut hilang.
     */
    public function rebuild(Employee $employee, CarbonInterface $date): ?Attendance
    {
        $date = Carbon::parse($date)->startOfDay();

        [$from, $to] = $this->window($employee, $date);

        $punches = $employee->punches()
            ->where('status', 'matched')
            ->whereBetween('punched_at', [$from, $to])
            ->orderBy('punched_at')
            ->get();

        if ($punches->isEmpty()) {
            return null;
        }

        $existing = $employee->attendances()
            ->whereDate('work_date', $date->toDateString())
            ->first();

        $times = $punches->pluck('punched_at');

        $keptIn = $this->humanEntered($existing?->clock_in, $times);
        $keptOut = $this->humanEntered($existing?->clock_out, $times);

        if ($keptIn) {
            // Jam masuknya sudah ditetapkan manusia, jadi punch mana pun sesudahnya
            // adalah kepulangan — bukan kedatangan.
            $after = $times->filter(fn (CarbonInterface $time) => $time->greaterThan($keptIn));

            $clockIn = $keptIn->format('H:i');
            $clockOut = $keptOut?->format('H:i')
                ?? $after->last()?->format('H:i')
                ?? $existing?->clock_out?->format('H:i');
        } else {
            $first = $times->first();
            $last = $times->last();

            $clockIn = $first->format('H:i');
            $clockOut = $keptOut?->format('H:i')
                ?? ($last->equalTo($first) ? null : $last->format('H:i'));
        }

        // Catatannya ikut dibawa: ia menyimpan alasan koreksi, dan membiarkannya
        // hilang membuat jam yang tidak berasal dari mesin jadi tak bisa dijelaskan.
        return $this->resolver->resolve($employee, $date, $clockIn, $clockOut, $existing?->note);
    }

    /**
     * Hitung ulang absensi setelah satu punch tidak lagi dihitung (ditandai
     * "diabaikan" di Log Punch).
     *
     * Tidak cukup memanggil rebuild() begitu saja. Bila punch itu satu-satunya di
     * hari tersebut, rebuild() memilih tidak menyentuh apa pun — penjagaannya agar
     * absensi yang diisi tangan tidak terhapus — sehingga jam yang justru baru saja
     * dinyatakan keliru tetap terpampang di papan harian. Karena itu jam yang nilainya
     * memang BERASAL dari punch itu dikosongkan lebih dulu, sedangkan jam yang tidak
     * cocok dengannya dibiarkan: itu tulisan manusia, bukan bekas punch ini.
     */
    public function rebuildAfterIgnoring(Employee $employee, AttendancePunch $punch): ?Attendance
    {
        $date = $this->workDateFor($employee, $punch->punched_at);

        $attendance = $employee->attendances()
            ->whereDate('work_date', $date->toDateString())
            ->first();

        if ($attendance) {
            $time = Carbon::parse($punch->punched_at)->format('H:i');

            $cleared = collect(['clock_in', 'clock_out'])
                ->filter(fn (string $column) => $attendance->{$column}?->format('H:i') === $time)
                ->mapWithKeys(fn (string $column) => [$column => null])
                ->all();

            if ($cleared !== []) {
                $attendance->forceFill($cleared)->save();
            }
        }

        // Punch yang tersisa mengisi kembali sisi yang kosong. Kalau tidak ada lagi
        // yang tersisa, statusnya tetap harus dihitung ulang dari jam yang sekarang —
        // hari yang jam masuknya baru saja hilang bukan lagi "hadir".
        return $this->rebuild($employee, $date)
            ?? ($attendance ? $this->resolver->reprocess($employee, $date) : null);
    }

    /**
     * Jam pada baris absensi yang tidak cocok dengan satu pun punch di hari itu.
     *
     * Nilai semacam itu tidak mungkin datang dari mesin, jadi ia pasti ditulis
     * manusia — dan feed mesin tidak boleh menghapusnya. Pencocokannya memakai jam
     * dan menit, karena itulah satuan yang disimpan resolver.
     *
     * @param  Collection<int, CarbonInterface>  $punchTimes
     */
    private function humanEntered(?CarbonInterface $value, Collection $punchTimes): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $value = Carbon::parse($value);

        $fromMachine = $punchTimes->contains(
            fn (CarbonInterface $time) => $time->format('H:i') === $value->format('H:i'),
        );

        return $fromMachine ? null : $value;
    }

    /**
     * Work date yang memiliki sebuah momen absen bagi karyawan ini.
     *
     * Untuk shift lintas tengah malam, pukul 06:00 masih bagian dari shift yang
     * dimulai kemarin pukul 22:00 — absensinya menempel pada work_date kemarin, bukan
     * hari ini. Aturannya sengaja memakai window() yang sama dengan feed mesin sidik
     * jari, supaya absen mandiri (selfie) dan absen mesin tidak pernah jatuh ke
     * tanggal yang berbeda untuk shift yang sama.
     */
    public function workDateFor(Employee $employee, CarbonInterface $moment): Carbon
    {
        $moment = Carbon::parse($moment);
        $today = $moment->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();

        [$from, $to] = $this->window($employee, $yesterday);

        // Setengah terbuka: batas atas sudah menjadi milik hari berikutnya, sehingga
        // satu momen tidak pernah diklaim dua tanggal sekaligus.
        return ($moment->greaterThanOrEqualTo($from) && $moment->lessThan($to)) ? $yesterday : $today;
    }

    /**
     * The datetime window that "owns" punches for a work date. For a scheduled shift
     * it is the shift window (handles overnight) plus a margin; otherwise the calendar day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Employee $employee, CarbonInterface $date): array
    {
        $date = Carbon::parse($date)->startOfDay();

        $shift = $this->shiftOn($employee, $date);

        if (! $shift) {
            return [$date->copy(), $date->copy()->addDay()];
        }

        $w = $shift->windowFor($date);
        $start = $w['start']->copy()->subHours(self::MARGIN_BEFORE_HOURS);

        // Overtime can push the clock-out well past the shift end — even across midnight
        // for a day shift. Extend the window to capture it, but never into the next
        // scheduled shift, so an overtime punch is owned by exactly one day.
        $end = $w['end']->copy()->addHours(self::MAX_OVERTIME_HOURS);

        $nextShift = $this->shiftOn($employee, $date->copy()->addDay());

        if ($nextShift) {
            $nextStart = $nextShift->windowFor($date->copy()->addDay())['start']
                ->copy()->subHours(self::MARGIN_BEFORE_HOURS);

            if ($end->greaterThan($nextStart)) {
                $end = $nextStart;
            }
        }

        // Never shrink below the normal late-departure buffer.
        $minEnd = $w['end']->copy()->addHours(self::MARGIN_AFTER_HOURS);

        return [$start, $end->lessThan($minEnd) ? $minEnd : $end];
    }

    private function shiftOn(Employee $employee, CarbonInterface $date): ?\App\Models\Shift
    {
        $schedule = $employee->schedules()
            ->whereDate('work_date', Carbon::parse($date)->toDateString())
            ->with('shift')
            ->first();

        return ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;
    }
}
