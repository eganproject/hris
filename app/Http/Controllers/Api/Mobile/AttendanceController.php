<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\MobileAttendanceLog;
use App\Models\MobileDevice;
use App\Services\DefaultOfficeSchedule;
use App\Services\Mobile\MobileAttendanceService;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly MobileAttendanceService $service,
        private readonly DefaultOfficeSchedule $officeSchedule,
    ) {}

    /**
     * Keadaan absen saat ini untuk layar Beranda: sesi terbuka, jadwal hari ini,
     * mode (kantor/WFH), dan titik kantor untuk pengecekan radius di HP.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $now = now();
        $open = $this->service->openSession($employee, $now);

        $date = $open ? $open->workDate->copy() : $now->copy()->startOfDay();
        $attendance = $open?->attendance ?? $employee->attendances()->whereDate('work_date', $date->toDateString())->first();
        $remote = $this->service->remoteStatusOn($employee, $date);
        $branch = $employee->branch;

        $schedule = $employee->schedules()->whereDate('work_date', $date->toDateString())->with('shift')->first()
            ?? $this->officeSchedule->scheduleFor($employee, $date);
        $shift = ($schedule && ! $schedule->is_day_off) ? $schedule->shift : null;

        $maxMinutes = MobileAttendanceService::sessionMaxMinutes();

        return response()->json(['data' => [
            'server_time' => $now->toIso8601String(),
            'work_date' => $date->toDateString(),
            'session' => $open ? [
                'open' => true,
                'clock_in_at' => $open->clockInAt->toIso8601String(),
                'expires_at' => $open->clockInAt->copy()->addMinutes($maxMinutes)->toIso8601String(),
            ] : ['open' => false],
            'next_action' => $open ? 'out' : 'in',
            'attendance' => $attendance ? $this->attendanceJson($attendance) : null,
            'schedule' => [
                'is_day_off' => (bool) ($schedule?->is_day_off ?? ! $shift),
                'shift' => $shift ? [
                    'name' => $shift->name,
                    'start' => substr((string) $shift->start_time, 0, 5),
                    'end' => substr((string) $shift->end_time, 0, 5),
                ] : null,
            ],
            'mode' => $remote?->value ?? 'office',
            'mode_label' => $remote?->label() ?? 'Kantor',
            'location' => $branch ? [
                'branch_id' => $branch->id,
                'name' => $branch->name,
                'enabled' => (bool) $branch->mobile_attendance_enabled,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'radius_m' => $branch->geofence_radius_m,
                'strict' => (bool) $branch->geofence_strict,
                'has_wifi' => ! empty($branch->wifi_bssids),
            ] : null,
            'settings' => [
                'session_max_minutes' => $maxMinutes,
                'blink_timeout_seconds' => 10,
                'max_queue_age_hours' => MobileAttendanceService::MAX_QUEUE_AGE_HOURS,
            ],
        ]]);
    }

    /**
     * Absen masuk/pulang. Selalu 200 untuk kiriman yang sah secara format, baik
     * diterima maupun ditolak aturan — supaya antrean offline tahu kirimannya sudah
     * diproses server dan tidak perlu diulang. Hasilnya di data.result.
     */
    public function clock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'type' => ['required', 'in:in,out'],
            'captured_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'blink_verified' => ['sometimes', 'boolean'],
            'is_mock_location' => ['required', 'boolean'],
            'is_rooted' => ['required', 'boolean'],
            'wifi_ssid' => ['nullable', 'string', 'max:255'],
            'wifi_bssid' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        foreach (['blink_verified', 'is_mock_location', 'is_rooted'] as $key) {
            $data[$key] = $request->boolean($key);
        }

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        if (! $device->consented_at) {
            return response()->json([
                'message' => 'Setujui dulu penyimpanan selfie dan lokasi untuk absensi.',
                'code' => 'consent_required',
            ], 403);
        }

        $log = $this->service->record($this->employee($request), $device, $data, $request->file('photo'));

        return response()->json(['data' => $this->logJson($log->fresh(['attendance', 'branch']))]);
    }

    /**
     * Riwayat absensi per bulan (default bulan ini), terbaru dulu.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth() : now()->startOfMonth();
        $employee = $this->employee($request);

        $rows = $employee->attendances()
            ->whereBetween('work_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->with('shift')
            ->orderByDesc('work_date')
            ->get();

        $logs = $employee->mobileAttendanceLogs()
            ->whereIn('attendance_id', $rows->pluck('id'))
            ->where('result', MobileAttendanceLog::RESULT_ACCEPTED)
            ->get()
            ->groupBy('attendance_id');

        return response()->json(['data' => [
            'month' => $month->format('Y-m'),
            'summary' => [
                'present' => $rows->filter(fn (Attendance $a) => $a->status->isWorked())->count(),
                'late' => $rows->where('status', \App\Enums\AttendanceStatus::Late)->count(),
                'absent' => $rows->where('status', \App\Enums\AttendanceStatus::Absent)->count(),
                'late_minutes' => (int) $rows->sum('late_minutes'),
            ],
            'items' => $rows->map(fn (Attendance $a) => $this->attendanceJson($a) + [
                'flags' => collect($logs->get($a->id, []))->flatMap(fn (MobileAttendanceLog $l) => $l->flagLabels())->unique()->values(),
            ])->values(),
        ]]);
    }

    public function photo(Request $request, Attendance $attendance, string $side): StreamedResponse
    {
        abort_unless($attendance->employee_id === $this->employee($request)->id, 403);

        $path = $attendance->{"clock_{$side}_photo_path"};
        $disk = Storage::disk(Attendance::SELFIE_DISK);

        abort_unless($path && $disk->exists($path), 404, 'Foto tidak ditemukan.');

        return PrivateFile::response($disk, $path, 'selfie.jpg', 'image/jpeg', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceJson(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'work_date' => $a->work_date->toDateString(),
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'shift' => $a->shift?->name,
            'clock_in' => $a->clock_in?->toIso8601String(),
            'clock_out' => $a->clock_out?->toIso8601String(),
            'late_minutes' => $a->late_minutes,
            'early_leave_minutes' => $a->early_leave_minutes,
            'work_minutes' => $a->work_minutes,
            'overtime_minutes' => $a->overtime_minutes,
            'has_photo_in' => $a->clock_in_photo_path !== null,
            'has_photo_out' => $a->clock_out_photo_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logJson(MobileAttendanceLog $log): array
    {
        return [
            'client_uuid' => $log->client_uuid,
            'type' => $log->type,
            'result' => $log->result,
            'reject_code' => $log->reject_code,
            'message' => $log->isAccepted()
                ? ($log->type === 'in' ? 'Absen masuk tercatat' : 'Absen pulang tercatat')
                : $log->reject_reason,
            'captured_at' => $log->captured_at->toIso8601String(),
            'work_date' => $log->work_date?->toDateString(),
            'mode' => $log->mode,
            'mode_label' => $log->mode_label,
            'branch' => $log->branch?->name,
            'distance_m' => $log->distance_m,
            'within_radius' => $log->within_radius,
            'wifi_matched' => $log->wifi_matched,
            'blink_verified' => $log->blink_verified,
            'flags' => $log->flags ?? [],
            'flag_labels' => $log->flagLabels(),
            // Tidak ada lagi persetujuan HR; tetap dikirim false untuk APK lama.
            'needs_review' => false,
            'flagged' => ! empty($log->flags),
            'attendance' => $log->attendance ? $this->attendanceJson($log->attendance) : null,
        ];
    }

    private function employee(Request $request): Employee
    {
        return $request->attributes->get('employee') ?? $request->user()->employee;
    }
}
