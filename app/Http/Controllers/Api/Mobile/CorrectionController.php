<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Support\ApprovalNotifier;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Koreksi absensi dari aplikasi HP — aturan sama dengan web (bukti gambar wajib,
 * minimal satu jam diisi), disetujui HR lewat menu Koreksi Absensi.
 */
class CorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corrections = $request->user()->employee->attendanceCorrections()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AttendanceCorrection $c) => $this->json($c));

        return response()->json(['data' => $corrections]);
    }

    public function store(StoreAttendanceCorrectionRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $attachment = $request->file('attachment');

        $correction = $employee->attendanceCorrections()->create([
            ...$request->safe()->except('attachment'),
            'attachment_path' => $attachment->store("correction-attachments/{$employee->id}", AttendanceCorrection::ATTACHMENT_DISK),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => PrivateFile::detectedMime($attachment),
            'attachment_size' => $attachment->getSize(),
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        app(ApprovalNotifier::class)->correctionSubmitted($correction);

        return response()->json(['data' => $this->json($correction)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(AttendanceCorrection $c): array
    {
        return [
            'id' => $c->id,
            'work_date' => $c->work_date?->toDateString(),
            'requested_clock_in' => $c->requested_clock_in ? substr((string) $c->requested_clock_in, 0, 5) : null,
            'requested_clock_out' => $c->requested_clock_out ? substr((string) $c->requested_clock_out, 0, 5) : null,
            'reason' => $c->reason,
            'status' => $c->status,
            'status_label' => $c->status_label,
            'decision_notes' => $c->decision_notes,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
