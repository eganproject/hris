<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Http\Requests\StoreMyLeaveRequest;
use App\Models\AttendanceCorrection;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\ShiftSwapRequest;
use App\Services\LeaveBalanceService;
use App\Services\LeaveWorkflow;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Tab "Pengajuan": ringkasan saldo cuti, daftar semua pengajuan milik sendiri
 * (cuti/izin/sakit, lembur, tukar jadwal, koreksi), dan pengajuan cuti/izin/sakit.
 * Aturan pengajuan sama persis dengan web (StoreMyLeaveRequest + LeaveWorkflow).
 */
class PengajuanController extends HrController
{
    public function __construct(
        private readonly LeaveWorkflow $workflow,
        private readonly LeaveBalanceService $balances,
    ) {}

    /** Kartu saldo + 5 pengajuan terakhir untuk layar utama Pengajuan. */
    public function summary(): JsonResponse
    {
        $employee = $this->employee();

        return $this->ok([
            'balances' => $this->balanceRows(),
            'recent' => $this->items(null)->take(5)->values(),
            'has_supervisor' => (bool) $employee->manager_id,
        ]);
    }

    /** Semua pengajuan milik sendiri, terbaru dulu. ?type=cuti|izin|sakit|lembur|tukar|koreksi */
    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString() ?: null;
        $items = $this->items($type);

        $perPage = 20;
        $page = max(1, $request->integer('page', 1));

        return response()->json([
            'data' => $items->forPage($page, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($items->count() / $perPage)),
                'per_page' => $perPage,
                'total' => $items->count(),
            ],
        ]);
    }

    public function leaveTypes(): JsonResponse
    {
        $employee = $this->employee();
        $year = now()->year;

        return $this->ok(
            LeaveType::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (LeaveType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'category' => Present::leaveCategory($type),
                    'requires_attachment' => $type->attendance_status === AttendanceStatus::Sick,
                    'counts_against_balance' => $type->counts_against_balance,
                    'remaining' => $type->counts_against_balance ? $this->balances->remaining($employee, $type, $year) : null,
                ])
                ->values(),
            200,
            ['meta' => [
                'self_backdate_days' => LeaveRequest::SELF_BACKDATE_DAYS,
                'attachment_max_mb' => LeaveRequest::ATTACHMENT_MAX_MB,
            ]],
        );
    }

    public function storeLeave(StoreMyLeaveRequest $request): JsonResponse
    {
        $leave = $this->workflow->submit($this->employee(), $request->validated());

        return response()->json([
            'message' => $leave->supervisor_id ? 'Pengajuan terkirim ke atasan langsung.' : 'Pengajuan terkirim ke HR.',
            'data' => Present::leave($leave->fresh()),
        ], 201);
    }

    public function showLeave(LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->employee_id === $this->employee()->id, 404);

        return $this->ok(Present::leave($leaveRequest));
    }

    public function cancelLeave(LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->employee_id === $this->employee()->id && $leaveRequest->status->isPending(), 403, 'Pengajuan ini sudah tidak bisa dibatalkan.');

        $this->workflow->cancel($leaveRequest);

        return $this->message('Pengajuan dibatalkan.', Present::leave($leaveRequest->fresh()));
    }

    /** Sama dengan web: hanya pengaju, dan hanya selama belum disetujui. */
    public function destroyLeave(LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->employee_id === $this->employee()->id, 404);
        abort_if($leaveRequest->status === LeaveRequestStatus::Approved, 403, 'Cuti yang sudah disetujui tidak bisa dihapus.');

        if ($leaveRequest->hasAttachment()) {
            Storage::disk(LeaveRequest::ATTACHMENT_DISK)->delete($leaveRequest->attachment_path);
        }

        $leaveRequest->delete();

        return $this->message('Pengajuan dihapus.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function items(?string $type): Collection
    {
        $employee = $this->employee();
        $limit = 200;
        $items = collect();

        if (in_array($type, [null, 'cuti', 'izin', 'sakit'], true)) {
            $items = $items->merge(
                LeaveRequest::query()->where('employee_id', $employee->id)
                    ->with(['leaveType', 'supervisor'])->latest('id')->limit($limit)->get()
                    ->map(fn (LeaveRequest $leave) => Present::leave($leave))
                    ->when($type !== null, fn (Collection $c) => $c->where('category', $type))
            );
        }

        if (in_array($type, [null, 'lembur'], true)) {
            $items = $items->merge(
                OvertimeApproval::query()->where('employee_id', $employee->id)->latest('id')->limit($limit)->get()
                    ->map(fn (OvertimeApproval $o) => Present::overtime($o))
            );
        }

        if (in_array($type, [null, 'tukar'], true)) {
            $items = $items->merge(
                ShiftSwapRequest::query()->involving($employee->id)->with(['requester', 'partner'])->latest('id')->limit($limit)->get()
                    ->map(fn (ShiftSwapRequest $s) => Present::swap($s, $employee))
            );
        }

        if (in_array($type, [null, 'koreksi'], true)) {
            $items = $items->merge(
                AttendanceCorrection::query()->where('employee_id', $employee->id)->latest('id')->limit($limit)->get()
                    ->map(fn (AttendanceCorrection $c) => Present::correction($c))
            );
        }

        return $items->sortByDesc('submitted_at')->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function balanceRows(): array
    {
        $employee = $this->employee();
        $year = now()->year;

        return LeaveType::query()
            ->where('is_active', true)
            ->where('counts_against_balance', true)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $type) use ($employee, $year) {
                $pendingDays = LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->whereIn('status', [LeaveRequestStatus::PendingSupervisor->value, LeaveRequestStatus::PendingHr->value])
                    ->whereYear('start_date', $year)
                    ->get()
                    ->sum(fn (LeaveRequest $leave) => $leave->days);

                return [
                    'leave_type_id' => $type->id,
                    'name' => $type->name,
                    'year' => $year,
                    'quota' => $this->balances->quota($employee, $type, $year),
                    'used' => $this->balances->used($employee, $type, $year),
                    'remaining' => $this->balances->remaining($employee, $type, $year),
                    'pending_days' => $pendingDays,
                ];
            })
            ->values()
            ->all();
    }
}
