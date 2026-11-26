<?php

namespace App\Services\Tablet;

use App\Models\Attendance;
use App\Models\AttendanceTablet;
use App\Models\Employee;
use App\Models\TabletAttendanceLog;
use App\Services\AttendanceResolver;
use App\Services\Mobile\MobileAttendanceService;
use App\Services\Push\AttendancePushNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Absen otomatis dari tablet wajah (keputusan Ega 2026-11-26).
 *
 * - Tablet mencocokkan wajah di dalam perangkat lalu mengirim karyawan, skor, dan
 *   foto bukti. Sejak tombol Absen Masuk / Absen Pulang, tablet juga mengirim
 *   pilihan karyawan (requested_type) dan pilihan itu yang dipakai. Tanpa pilihan
 *   (versi lama tablet) server menebak:
 *   sesi terbuka (sudah masuk, belum pulang, dalam batas sesi 23j50m) = pulang;
 *   selain itu = masuk. Sesi dibaca dari tabel absensi, jadi boleh dicampur
 *   dengan mesin X100-C atau koreksi.
 * - Pindai ulang dalam 1 menit diabaikan (tidak mengubah absensi).
 * - Pindai lagi setelah pulang di hari yang sama = jam pulang diperbarui.
 * - NIK + PIN, skor wajah tipis, dan cek wajah asli yang meragukan diberi tanda
 *   untuk HR tanpa perlu persetujuan.
 * - "Bukan saya" dicatat sebagai dibatalkan, tanpa menulis absensi.
 */
class TabletAttendanceService
{
    /** Antrean offline lebih tua dari ini tidak diterima lagi (harus lewat koreksi). */
    public const MAX_QUEUE_AGE_HOURS = 72;

    /** Selisih jam terima - jam absen di atas ini = ditandai "terkirim terlambat". */
    public const OFFLINE_FLAG_MINUTES = 5;

    public function __construct(
        private readonly AttendanceResolver $resolver,
        private readonly MobileAttendanceService $sessions,
        private readonly AttendancePushNotifier $push,
    ) {}

    /**
     * @param  array{client_uuid: string, employee_id: int, captured_at: string, method: string, match_score?: ?float, match_margin?: ?float, liveness_checked?: bool, liveness_score?: ?float, not_me?: bool, requested_type?: ?string}  $data
     */
    public function record(AttendanceTablet $tablet, array $data, ?UploadedFile $photo): TabletAttendanceLog
    {
        $existing = TabletAttendanceLog::query()->where('client_uuid', $data['client_uuid'])->first();

        if ($existing) {
            abort_unless($existing->attendance_tablet_id === $tablet->id, 409, 'ID absen sudah dipakai.');

            return $existing;
        }

        return DB::transaction(function () use ($tablet, $data, $photo) {
            // Kunci baris karyawan supaya dua kiriman bersamaan (dua tablet, atau
            // antrean offline + pindai baru) tidak sama-sama membuka sesi.
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();

            $again = TabletAttendanceLog::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($again) {
                return $again;
            }

            return $this->evaluate($tablet, $employee, $data, $photo);
        });
    }

    private function evaluate(AttendanceTablet $tablet, Employee $employee, array $data, ?UploadedFile $photo): TabletAttendanceLog
    {
        $now = now();
        $flags = [];

        $captured = Carbon::parse($data['captured_at'])->setTimezone(config('app.timezone'));

        if ($captured->greaterThan($now->copy()->addMinutes(2))) {
            $flags[] = 'clock_skew';
            $captured = $now->copy();
        }

        if ($now->diffInMinutes($captured, true) > self::OFFLINE_FLAG_MINUTES) {
            $flags[] = 'offline_delay';
        }

        $captured = $captured->copy()->startOfSecond();
        $method = $data['method'] === 'pin' ? 'pin' : 'face';

        $log = new TabletAttendanceLog([
            'client_uuid' => $data['client_uuid'],
            'attendance_tablet_id' => $tablet->id,
            'employee_id' => $employee->id,
            'branch_id' => $tablet->branch_id,
            'method' => $method,
            'captured_at' => $captured,
            'received_at' => $now,
            'match_score' => $data['match_score'] ?? null,
            'match_margin' => $data['match_margin'] ?? null,
            'liveness_checked' => (bool) ($data['liveness_checked'] ?? false),
            'liveness_score' => $data['liveness_score'] ?? null,
        ]);

        if (! empty($data['not_me'])) {
            return $this->finish($log, $photo, TabletAttendanceLog::RESULT_CANCELLED, null, 'not_me', 'Karyawan menekan "Bukan saya".');
        }

        if ($employee->employment_status !== 'active') {
            return $this->finish($log, $photo, TabletAttendanceLog::RESULT_REJECTED, null, 'inactive', 'Karyawan sudah tidak aktif.');
        }

        if ($method === 'pin' && ! TabletSettings::pinEnabled()) {
            return $this->finish($log, $photo, TabletAttendanceLog::RESULT_REJECTED, null, 'pin_disabled', 'Absen pakai NIK + PIN sedang dimatikan.');
        }

        if ($captured->lessThan($now->copy()->subHours(self::MAX_QUEUE_AGE_HOURS))) {
            return $this->finish($log, $photo, TabletAttendanceLog::RESULT_REJECTED, null, 'expired', 'Absen tersimpan di tablet lebih dari 3 hari sebelum terkirim. Ajukan koreksi absensi.');
        }

        // --- Tanda untuk HR ------------------------------------------------------
        if ($method === 'pin') {
            $flags[] = 'pin_fallback';
        } elseif ($log->match_score !== null && $log->match_score < TabletSettings::matchThreshold() + TabletSettings::LOW_MATCH_BAND) {
            $flags[] = 'low_match';
        }

        if ($log->liveness_checked && $log->liveness_score !== null && $log->liveness_score < TabletSettings::LIVENESS_DOUBTFUL) {
            $flags[] = 'liveness_doubtful';
        }

        if ($employee->branch_id !== $tablet->branch_id) {
            $flags[] = 'other_branch';
        }

        // --- Masuk atau pulang -----------------------------------------------------
        $requested = $data['requested_type'] ?? null;

        $decided = in_array($requested, ['in', 'out'], true)
            ? $this->decideChosen($employee, $captured, $requested, $flags)
            : $this->decideAutomatic($employee, $captured, $flags);

        if ($decided['ignore'] ?? false) {
            return $this->ignored($log, $photo, $decided['type'], $decided['workDate'], $decided['attendance'], $decided['reason']);
        }

        ['type' => $type, 'workDate' => $workDate, 'attendance' => $attendance] = $decided;

        $log->type = $type;
        $log->work_date = $workDate;

        // --- Tulis ke absensi ----------------------------------------------------
        $path = $photo?->store('attendance/tablet/'.$workDate->format('Y/m'), Attendance::SELFIE_DISK);

        $clockIn = $type === 'in' ? $captured->format('H:i') : $attendance?->clock_in?->format('H:i');
        $clockOut = $type === 'out' ? $captured->format('H:i') : $attendance?->clock_out?->format('H:i');

        $attendance = $this->resolver->resolve($employee, $workDate, $clockIn, $clockOut, $attendance?->note);

        if ($path) {
            $attendance->forceFill(["clock_{$type}_photo_path" => $path])->save();
        }

        $flags = array_values(array_unique($flags));

        $log->forceFill([
            'result' => TabletAttendanceLog::RESULT_ACCEPTED,
            'attendance_id' => $attendance->id,
            'photo_path' => $path,
            'flags' => $flags ?: null,
        ])->save();

        $this->push->clocked($employee, $type, $captured, AttendancePushNotifier::SOURCE_TABLET, $tablet->name);

        return $log;
    }

    /**
     * Versi lama tablet (tanpa tombol): server menebak masuk atau pulang dari sesi.
     *
     * @param  list<string>  $flags
     * @return array{type: string, workDate: Carbon, attendance: ?Attendance, ignore?: bool, reason?: string}
     */
    private function decideAutomatic(Employee $employee, Carbon $captured, array &$flags): array
    {
        $repeat = TabletSettings::REPEAT_IGNORE_SECONDS;
        $open = $this->sessions->openSession($employee, $captured);

        if ($open) {
            if ($open->clockInAt->diffInSeconds($captured, true) < $repeat) {
                return ['ignore' => true, 'type' => 'in', 'workDate' => $open->workDate, 'attendance' => $open->attendance, 'reason' => 'Sudah absen masuk pukul '.$open->clockInAt->format('H:i').'.'];
            }

            return ['type' => 'out', 'workDate' => $open->workDate->copy(), 'attendance' => $open->attendance];
        }

        return $this->outOrInToday($employee, $captured, $flags, 'in');
    }

    /**
     * Karyawan memilih sendiri lewat tombol Absen Masuk / Absen Pulang (Ega,
     * 2026-11-26). Pilihannya dihormati; tablet sudah bertanya lebih dulu bila
     * pilihan itu janggal, jadi di sini hanya kiriman ganda yang diabaikan.
     *
     * @param  list<string>  $flags
     * @return array{type: string, workDate: Carbon, attendance: ?Attendance, ignore?: bool, reason?: string}
     */
    private function decideChosen(Employee $employee, Carbon $captured, string $requested, array &$flags): array
    {
        $open = $this->sessions->openSession($employee, $captured);
        $today = $captured->copy()->startOfDay();

        if ($requested === 'out') {
            if ($open) {
                if ($open->clockInAt->diffInSeconds($captured, true) < TabletSettings::REPEAT_IGNORE_SECONDS) {
                    return ['ignore' => true, 'type' => 'in', 'workDate' => $open->workDate, 'attendance' => $open->attendance, 'reason' => 'Baru saja absen masuk pukul '.$open->clockInAt->format('H:i').'.'];
                }

                return ['type' => 'out', 'workDate' => $open->workDate->copy(), 'attendance' => $open->attendance];
            }

            return $this->outOrInToday($employee, $captured, $flags, 'out');
        }

        // Pilih MASUK. Sesi terbuka dari hari sebelumnya (lupa absen pulang) tidak
        // menghalangi: hari ini dibuka sebagai absensi baru.
        if ($open && $open->workDate->isSameDay($today)) {
            return ['ignore' => true, 'type' => 'in', 'workDate' => $open->workDate, 'attendance' => $open->attendance, 'reason' => 'Sudah absen masuk pukul '.$open->clockInAt->format('H:i').'.'];
        }

        $attendance = $employee->attendances()->whereDate('work_date', $today->toDateString())->first();

        if ($attendance?->clock_in) {
            return ['ignore' => true, 'type' => 'in', 'workDate' => $today, 'attendance' => $attendance, 'reason' => 'Sudah absen masuk pukul '.Carbon::parse($attendance->clock_in)->format('H:i').'.'];
        }

        return ['type' => 'in', 'workDate' => $today, 'attendance' => $attendance];
    }

    /**
     * Tidak ada sesi terbuka: tentukan dari absensi hari ini.
     *
     * @param  list<string>  $flags
     * @return array{type: string, workDate: Carbon, attendance: ?Attendance, ignore?: bool, reason?: string}
     */
    private function outOrInToday(Employee $employee, Carbon $captured, array &$flags, string $whenEmpty): array
    {
        $repeat = TabletSettings::REPEAT_IGNORE_SECONDS;
        $workDate = $captured->copy()->startOfDay();
        $attendance = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

        if ($attendance?->clock_in && $attendance->clock_out) {
            // Sudah masuk dan pulang hari ini: pindai lagi = jam pulang diperbarui.
            $lastOut = Carbon::parse($attendance->clock_out);

            if ($captured->lessThanOrEqualTo($lastOut) || $lastOut->diffInSeconds($captured, true) < $repeat) {
                return ['ignore' => true, 'type' => 'out', 'workDate' => $workDate, 'attendance' => $attendance, 'reason' => 'Sudah absen pulang pukul '.$lastOut->format('H:i').'.'];
            }

            $flags[] = 'repeat_out';

            return ['type' => 'out', 'workDate' => $workDate, 'attendance' => $attendance];
        }

        if ($attendance?->clock_in) {
            // Masuk hari ini sudah lewat batas sesi tanpa pulang: catat sebagai pulang
            // terlambat agar tidak hilang; HR melihatnya di riwayat.
            return ['type' => 'out', 'workDate' => $workDate, 'attendance' => $attendance];
        }

        if ($whenEmpty === 'out') {
            // Karyawan memilih pulang (dan sudah mengonfirmasi di tablet) padahal
            // belum ada jam masuk. Dicatat apa adanya dan ditandai untuk HR.
            $flags[] = 'no_clock_in';
        }

        return ['type' => $whenEmpty, 'workDate' => $workDate, 'attendance' => $attendance];
    }

    private function ignored(TabletAttendanceLog $log, ?UploadedFile $photo, string $type, Carbon $workDate, ?Attendance $attendance, string $reason): TabletAttendanceLog
    {
        $log->type = $type;
        $log->work_date = $workDate;
        $log->attendance_id = $attendance?->id;

        return $this->finish($log, $photo, TabletAttendanceLog::RESULT_IGNORED, $type, 'repeat', $reason);
    }

    private function finish(TabletAttendanceLog $log, ?UploadedFile $photo, string $result, ?string $type, string $code, string $reason): TabletAttendanceLog
    {
        $log->forceFill([
            'type' => $type ?? $log->type,
            'result' => $result,
            'reject_code' => $code,
            'reject_reason' => $reason,
            'photo_path' => $photo?->store('attendance/tablet-other/'.now()->format('Y/m'), Attendance::SELFIE_DISK),
        ])->save();

        return $log;
    }
}
