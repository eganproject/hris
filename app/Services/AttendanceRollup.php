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

        // Sebuah punch hanya boleh dimiliki SATU tanggal kerja.
        //
        // Jendela di atas sengaja longgar supaya tidak ada tap yang tercecer, tapi
        // kelonggaran itu membuat jendela dua hari berturut-turut bisa bertumpuk: tap
        // pulang shift malam pukul 06:00 berada di dalam jendela tanggal kemarin, dan
        // juga di dalam jendela hari ini ketika hari ini tidak terjadwal — sebab hari
        // tanpa jadwal mengklaim satu hari kalender penuh.
        //
        // Akibatnya tap pulang itu dihitung dua kali: sekali dengan benar sebagai jam
        // pulang shift malam, sekali lagi sebagai absensi baru keesokan harinya. Bila
        // tapnya dua kali — dan di lapangan itu sering terjadi — hari berikutnya bahkan
        // terbaca "Hadir" dengan jam masuk dan jam pulang berjarak beberapa detik.
        //
        // workDateFor() adalah aturan yang sudah dipakai absen mandiri untuk menjawab
        // "momen ini milik tanggal kerja yang mana". Dipakai di sini juga, kepemilikan
        // sebuah punch jadi tunggal dan dijawab oleh satu aturan yang sama.
        $punches = $punches->filter(
            fn (AttendancePunch $punch) => $this->workDateFor($employee, $punch->punched_at)->equalTo($date),
        );

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
            $after = $punches->filter(
                fn (AttendancePunch $punch) => $punch->punched_at->greaterThan($keptIn),
            );

            // Bila hari itu punya tap bertanda pulang, itu yang dipakai — bukan sekadar
            // tap terakhir. Tanpa ini, seorang karyawan yang jam masuknya dikoreksi lalu
            // menempelkan jari sekali lagi saat datang (jari pertama tidak terbaca)
            // akan tercatat pulang beberapa menit setelah masuk.
            //
            // Kalau tidak ada satu pun tap bertanda pulang, penandanya tidak memberi
            // keterangan apa-apa dan tap terakhir yang dipakai, seperti sebelumnya.
            $candidate = $punches->where('state', AttendancePunch::STATE_OUT)->isNotEmpty()
                ? $after->where('state', AttendancePunch::STATE_OUT)->last()
                : $after->last();

            $clockIn = $keptIn->format('H:i');
            $clockOut = $keptOut?->format('H:i')
                ?? $candidate?->punched_at->format('H:i')
                ?? $existing?->clock_out?->format('H:i');
        } else {
            [$machineIn, $machineOut] = $this->sidesFrom($punches);

            $clockIn = $machineIn?->format('H:i');
            $clockOut = $keptOut?->format('H:i') ?? $machineOut?->format('H:i');
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
     * Tentukan jam masuk dan jam pulang dari sekumpulan punch satu hari.
     *
     * Penanda masuk/pulang yang dipilih karyawan di mesin dipakai bila — dan hanya
     * bila — ia benar-benar MEMBEDAKAN, yaitu ada tap bertanda masuk dan ada tap
     * bertanda pulang pada hari itu. Kalau semuanya bertanda sama, penandanya tidak
     * memberi keterangan apa pun: bisa jadi prosedurnya terlewat, bisa jadi firmware
     * mesinnya memang selalu mengirim angka yang sama. Dalam keadaan itu urutan waktu
     * yang dipakai — aturan yang mungkin keliru pada kasus aneh, tapi tidak pernah
     * gagal total untuk semua orang sekaligus.
     *
     * Satu keadaan yang sekarang bisa dijawab jujur: hari yang hanya berisi tap
     * PULANG. Dulu tap itu dicatat sebagai jam masuk, sehingga orangnya terlihat baru
     * datang sore hari. Sekarang ia dicatat sebagai jam pulang tanpa jam masuk —
     * keadaan yang memang perlu koreksi, dan sekarang terlihat sebagai apa adanya.
     *
     * @param  Collection<int, AttendancePunch>  $punches  terurut menaik
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function sidesFrom($punches): array
    {
        $masuk = $punches->where('state', AttendancePunch::STATE_IN);
        $pulang = $punches->where('state', AttendancePunch::STATE_OUT);

        if ($masuk->isNotEmpty() && $pulang->isNotEmpty()) {
            $in = $masuk->first()->punched_at;
            $out = $pulang->last()->punched_at;

            // Penandanya hanya berarti bila kepulangannya memang SESUDAH kedatangannya.
            // Sebuah tap "pulang" yang keliru ditekan sebelum masuk — lalu tidak pernah
            // disusul tap pulang yang sebenarnya — akan menghasilkan jam pulang yang
            // mendahului jam masuk, yang oleh resolver digulirkan ke hari berikutnya
            // dan berubah menjadi rentang kerja hampir sehari penuh. Satu salah tekan
            // tidak boleh berakibat sebesar itu; dalam keadaan ini urutan waktu lebih
            // bisa dipercaya daripada penandanya.
            if ($out->greaterThan($in)) {
                return [$in, $out];
            }
        }

        // Hanya tap pulang sepanjang hari itu: jam masuknya memang tidak pernah
        // tercatat. Syarat "masuk kosong" penting — tanpa itu, pasangan yang baru saja
        // ditolak penjaga di atas akan jatuh ke sini dan jam masuknya ikut hilang.
        if ($masuk->isEmpty() && $pulang->isNotEmpty()) {
            return [null, $pulang->last()->punched_at];
        }

        $first = $punches->first()->punched_at;
        $last = $punches->last()->punched_at;

        return [$first, $last->equalTo($first) ? null : $last];
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
