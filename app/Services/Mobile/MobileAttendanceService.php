<?php

namespace App\Services\Mobile;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\MobileAttendanceLog;
use App\Models\MobileDevice;
use App\Models\Setting;
use App\Services\AttendanceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Absen masuk/pulang dari aplikasi HP.
 *
 * Aturan (keputusan Ega 2026-11-25):
 * - Bukti: selfie (kamera langsung, tanpa cek kedip) + lokasi. Tanpa pencocokan wajah.
 * - Hari kantor: titik GPS dibandingkan radius Lokasi Kerja. Di luar radius =
 *   diberi tanda di sistem, tanpa perlu persetujuan HR (ditolak hanya bila lokasi memakai mode ketat). Wi-Fi
 *   kantor yang cocok dianggap bukti berada di kantor.
 * - WFH / dinas luar: lokasi dicatat, tanpa radius.
 * - Lokasi palsu / HP root: ditolak, percobaannya tetap dicatat.
 * - Setelah absen masuk, absen masuk berikutnya terkunci sampai absen pulang,
 *   paling lama batas sesi (default 23j50m) sejak jam masuk. Sesi dibaca dari
 *   tabel absensi, jadi masuk/pulang boleh dicampur antara mesin dan HP.
 * - Jam dan buktinya ditulis ke tabel `attendances` yang sama dengan mesin.
 */
class MobileAttendanceService
{
    public const SESSION_SETTING_KEY = 'mobile_attendance_session_max_minutes';

    public const DEFAULT_SESSION_MINUTES = 23 * 60 + 50;

    /** Antrean offline lebih tua dari ini tidak diterima lagi (harus lewat koreksi). */
    public const MAX_QUEUE_AGE_HOURS = 72;

    /** Selisih jam terima - jam absen di atas ini = ditandai "terkirim terlambat". */
    public const OFFLINE_FLAG_MINUTES = 5;

    /** Akurasi GPS (meter) di atas ini ditandai. */
    public const LOW_ACCURACY_M = 100;

    public function __construct(private readonly AttendanceResolver $resolver) {}

    public static function sessionMaxMinutes(): int
    {
        $value = (int) Setting::get(self::SESSION_SETTING_KEY, (string) self::DEFAULT_SESSION_MINUTES);

        return $value > 0 ? $value : self::DEFAULT_SESSION_MINUTES;
    }

    /**
     * Sesi absen yang masih terbuka: baris absensi yang sudah punya jam masuk,
     * belum punya jam pulang, dan jam masuknya belum lewat batas sesi.
     *
     * Sumbernya tabel `attendances`, bukan hanya log aplikasi, supaya absen bisa
     * dicampur: masuk di mesin lalu pulang lewat HP, atau sebaliknya. Absen pulang
     * dari mesin/koreksi otomatis menutup sesi karena mengisi clock_out.
     */
    public function openSession(Employee $employee, ?Carbon $at = null): ?OpenSession
    {
        $at ??= now();

        $attendance = $employee->attendances()
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->where('clock_in', '<=', $at)
            ->where('clock_in', '>=', $at->copy()->subMinutes(self::sessionMaxMinutes()))
            ->latest('clock_in')
            ->first();

        if (! $attendance) {
            return null;
        }

        return new OpenSession(
            $attendance,
            Carbon::parse($attendance->clock_in),
            Carbon::parse($attendance->work_date)->startOfDay(),
        );
    }

    /**
     * Status kerja jarak jauh pada tanggal itu (WFH terjadwal / pengajuan WFH atau
     * dinas luar yang disetujui). Null = hari kantor.
     */
    public function remoteStatusOn(Employee $employee, Carbon $date): ?AttendanceStatus
    {
        $day = $date->toDateString();

        if ($employee->schedules()->whereDate('work_date', $day)->where('is_wfh', true)->exists()) {
            return AttendanceStatus::Wfh;
        }

        $remoteValues = array_map(fn (AttendanceStatus $s) => $s->value, AttendanceResolver::REMOTE_STATUSES);

        $leave = $employee->leaveRequests()
            ->approvedOn($day)
            ->whereHas('leaveType', fn ($query) => $query->whereIn('attendance_status', $remoteValues))
            ->with('leaveType')
            ->first();

        return $leave?->leaveType?->attendance_status;
    }

    /**
     * Catat satu absen dari aplikasi. Idempoten terhadap client_uuid: kiriman ulang
     * dari antrean offline mengembalikan catatan yang sama tanpa menulis ulang.
     *
     * @param  array{client_uuid: string, type: string, captured_at: string, latitude?: ?float, longitude?: ?float, accuracy?: ?float, blink_verified?: bool, is_mock_location?: bool, is_rooted?: bool, wifi_ssid?: ?string, wifi_bssid?: ?string}  $data
     */
    public function record(Employee $employee, MobileDevice $device, array $data, UploadedFile $photo): MobileAttendanceLog
    {
        $existing = MobileAttendanceLog::query()->where('client_uuid', $data['client_uuid'])->first();

        if ($existing) {
            abort_unless($existing->employee_id === $employee->id, 409, 'ID absen sudah dipakai.');

            return $existing;
        }

        return DB::transaction(function () use ($employee, $device, $data, $photo) {
            // Kunci baris karyawan supaya dua kiriman bersamaan (mis. antrean offline
            // + tombol baru) tidak sama-sama lolos pemeriksaan sesi.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $again = MobileAttendanceLog::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($again) {
                return $again;
            }

            return $this->evaluate($employee->fresh(), $device, $data, $photo);
        });
    }

    private function evaluate(Employee $employee, MobileDevice $device, array $data, UploadedFile $photo): MobileAttendanceLog
    {
        $now = now();
        $flags = [];
        $type = $data['type'];

        // --- Waktu -------------------------------------------------------------
        $captured = Carbon::parse($data['captured_at'])->setTimezone(config('app.timezone'));

        if ($captured->greaterThan($now->copy()->addMinutes(2))) {
            // Jam HP maju: jangan percaya, pakai jam server.
            $flags[] = 'clock_skew';
            $captured = $now->copy();
        }

        if ($now->diffInMinutes($captured, true) > self::OFFLINE_FLAG_MINUTES) {
            $flags[] = 'offline_delay';
        }

        $captured = $captured->copy()->startOfSecond();

        $log = new MobileAttendanceLog([
            'client_uuid' => $data['client_uuid'],
            'employee_id' => $employee->id,
            'mobile_device_id' => $device->id,
            'type' => $type,
            'mode' => 'office',
            'captured_at' => $captured,
            'received_at' => $now,
            'branch_id' => $employee->branch_id,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_m' => isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null,
            'wifi_ssid' => $data['wifi_ssid'] ?? null,
            'wifi_bssid' => $data['wifi_bssid'] ?? null,
            'blink_verified' => (bool) ($data['blink_verified'] ?? false),
            'is_mock_location' => (bool) ($data['is_mock_location'] ?? false),
            'is_rooted' => (bool) ($data['is_rooted'] ?? false),
            'employee_note' => isset($data['note']) ? mb_substr(trim((string) $data['note']), 0, 255) ?: null : null,
        ]);

        // --- Keamanan HP ---------------------------------------------------------
        if ($log->is_mock_location) {
            return $this->reject($log, $photo, 'mock_location', 'HP ini memakai aplikasi pengubah lokasi (fake GPS). Matikan aplikasinya, lalu coba lagi.');
        }

        if ($log->is_rooted) {
            return $this->reject($log, $photo, 'rooted', 'HP yang di-root/jailbreak tidak bisa dipakai untuk absen.');
        }

        if ($captured->lessThan($now->copy()->subHours(self::MAX_QUEUE_AGE_HOURS))) {
            return $this->reject($log, $photo, 'expired', 'Absen ini tersimpan lebih dari 3 hari sebelum terkirim. Ajukan koreksi absensi.');
        }

        // --- Sesi ----------------------------------------------------------------
        $open = $this->openSession($employee, $captured);

        if ($type === 'in') {
            if ($open) {
                return $this->reject($log, $photo, 'session_open', 'Kamu masih tercatat bekerja sejak '.$open->clockInAt->translatedFormat('d M H:i').'. Absen pulang dulu.');
            }

            $workDate = $captured->copy()->startOfDay();
            $existing = $employee->attendances()->whereDate('work_date', $workDate->toDateString())->first();

            if ($existing?->clock_in) {
                return $this->reject($log, $photo, 'already_clocked_in', 'Absen masuk untuk '.$workDate->translatedFormat('d M Y').' sudah tercatat pukul '.$existing->clock_in->format('H:i').'. Ajukan koreksi bila keliru.');
            }
        } else {
            if (! $open) {
                return $this->reject($log, $photo, 'no_session', 'Belum ada absen masuk yang terbuka (atau sudah lewat batas '.$this->sessionLabel().'). Ajukan koreksi absensi.');
            }

            $workDate = $open->workDate->copy();
            $existing = $open->attendance;
        }

        $log->work_date = $workDate;

        // --- Lokasi --------------------------------------------------------------
        $remote = $this->remoteStatusOn($employee, $workDate);
        $hasPoint = $log->latitude !== null && $log->longitude !== null;

        if ($remote) {
            $log->mode = $remote->value;

            if (! $hasPoint) {
                $flags[] = 'no_location';
            }
        } else {
            $branch = $employee->branch;

            if (! $branch || ! $branch->mobile_attendance_enabled) {
                return $this->reject($log, $photo, 'branch_disabled', 'Lokasi kerjamu belum memakai absen lewat aplikasi. Gunakan mesin absen, atau hubungi HR.');
            }

            if ($branch->matchesWifi($log->wifi_bssid)) {
                $log->wifi_matched = true;
            } elseif (! empty($branch->wifi_bssids)) {
                $log->wifi_matched = false;
            }

            if (! $branch->hasGeofence()) {
                $flags[] = 'no_geofence';
            } elseif (! $hasPoint) {
                if (! $log->wifi_matched) {
                    if ($branch->geofence_strict) {
                        return $this->reject($log, $photo, 'no_location', 'Lokasi HP tidak terbaca. Nyalakan GPS lalu coba lagi.');
                    }
                    $flags[] = 'no_location';
                }
            } else {
                $log->distance_m = $branch->distanceTo($log->latitude, $log->longitude);
                $log->within_radius = $log->distance_m <= $branch->geofence_radius_m;

                if (! $log->within_radius && ! $log->wifi_matched) {
                    if ($branch->geofence_strict) {
                        return $this->reject($log, $photo, 'outside_radius', "Kamu berada {$log->distance_m} m dari titik {$branch->name} (radius {$branch->geofence_radius_m} m). Absen hanya bisa di area kantor.");
                    }
                    $flags[] = 'outside_radius';
                }
            }

            if ($hasPoint && $log->accuracy_m !== null && $log->accuracy_m > self::LOW_ACCURACY_M && ! $log->wifi_matched) {
                $flags[] = 'low_accuracy';
            }
        }

        // Cek kedip dihapus (keputusan Ega 2026-11-25): selfie biasa sudah cukup,
        // jadi blink_verified hanya dicatat dan tidak lagi menandai absen.

        // --- Tulis ke absensi ----------------------------------------------------
        $path = $this->storePhoto($photo, $workDate);

        $clockIn = $type === 'in' ? $captured->format('H:i') : $existing?->clock_in?->format('H:i');
        $clockOut = $type === 'out' ? $captured->format('H:i') : $existing?->clock_out?->format('H:i');

        $attendance = $this->resolver->resolve($employee, $workDate, $clockIn, $clockOut, $existing?->note);

        $side = $type === 'in' ? 'in' : 'out';
        $old = $attendance->{"clock_{$side}_photo_path"};

        $attendance->forceFill([
            "clock_{$side}_photo_path" => $path,
            "clock_{$side}_latitude" => $log->latitude,
            "clock_{$side}_longitude" => $log->longitude,
            "clock_{$side}_accuracy_m" => $log->accuracy_m,
        ])->save();

        if ($old && $old !== $path && ! MobileAttendanceLog::query()->where('photo_path', $old)->exists()) {
            Storage::disk(Attendance::SELFIE_DISK)->delete($old);
        }

        $flags = array_values(array_unique($flags));

        $log->forceFill([
            'result' => MobileAttendanceLog::RESULT_ACCEPTED,
            'attendance_id' => $attendance->id,
            'photo_path' => $path,
            // Tanda hanya penanda di sistem; HR tidak perlu menyetujui apa pun
            // (keputusan Ega 2026-11-25).
            'flags' => $flags ?: null,
        ])->save();

        return $log;
    }

    private function reject(MobileAttendanceLog $log, UploadedFile $photo, string $code, string $reason): MobileAttendanceLog
    {
        $log->forceFill([
            'result' => MobileAttendanceLog::RESULT_REJECTED,
            'reject_code' => $code,
            'reject_reason' => $reason,
            'photo_path' => $photo->store('attendance/mobile-rejected/'.now()->format('Y/m'), Attendance::SELFIE_DISK),
        ])->save();

        return $log;
    }

    private function storePhoto(UploadedFile $photo, Carbon $workDate): string
    {
        return $photo->store('attendance/selfies/'.$workDate->format('Y/m'), Attendance::SELFIE_DISK);
    }

    public function sessionLabel(): string
    {
        $minutes = self::sessionMaxMinutes();

        return intdiv($minutes, 60).'j'.($minutes % 60 ? ' '.($minutes % 60).'m' : '');
    }
}
