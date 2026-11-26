<?php

namespace App\Http\Controllers\Api\Tablet;

use App\Http\Controllers\Controller;
use App\Models\AttendanceTablet;
use App\Models\TabletAttendanceLog;
use App\Services\Tablet\TabletAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Absen dari tablet. Tablet mengirim setelah layar hasil selesai (sehingga
 * "Bukan saya" masih sempat membatalkan); saat offline disimpan dulu di antrean
 * tablet dengan UUID yang sama lalu dikirim ulang.
 */
class AttendanceController extends Controller
{
    public function store(Request $request, TabletAttendanceService $service): JsonResponse
    {
        /** @var AttendanceTablet $tablet */
        $tablet = $request->user();

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'captured_at' => ['required', 'date'],
            'method' => ['required', 'in:face,pin'],
            'match_score' => ['nullable', 'numeric', 'between:-1,1'],
            'match_margin' => ['nullable', 'numeric', 'between:-2,2'],
            'liveness_checked' => ['nullable', 'boolean'],
            'liveness_score' => ['nullable', 'numeric', 'between:0,1'],
            'not_me' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $log = $service->record($tablet, $data, $request->file('photo'));

        return response()->json(['data' => $this->present($log)], $log->wasRecentlyCreated ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function present(TabletAttendanceLog $log): array
    {
        $attendance = $log->attendance;

        return [
            'client_uuid' => $log->client_uuid,
            'result' => $log->result,
            'type' => $log->type,
            'reason' => $log->reject_reason,
            'flags' => $log->flags ?? [],
            'captured_at' => $log->captured_at->toIso8601String(),
            'attendance' => $attendance ? [
                'work_date' => $attendance->work_date?->toDateString(),
                'clock_in' => $attendance->clock_in?->toIso8601String(),
                'clock_out' => $attendance->clock_out?->toIso8601String(),
                'late_minutes' => (int) $attendance->late_minutes,
                'work_minutes' => (int) $attendance->work_minutes,
                'status' => $attendance->status?->value,
            ] : null,
        ];
    }
}
