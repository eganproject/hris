<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\OvertimeApproval;
use App\Services\OvertimeWorkflow;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lembur Saya di aplikasi HP: daftar, ajukan, batalkan. Aturannya di OvertimeWorkflow. */
class OvertimeController extends HrController
{
    public function __construct(private readonly OvertimeWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $page = OvertimeApproval::query()
            ->where('employee_id', $this->employee()->id)
            ->latest('work_date')->latest('id')
            ->paginate(20);

        return $this->paginated($page, fn (OvertimeApproval $o) => Present::overtime($o));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], [
            'work_date' => 'tanggal lembur',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'reason' => 'uraian pekerjaan',
        ]);

        $overtime = $this->workflow->submit($this->employee(), $data);

        return response()->json([
            'message' => 'Pengajuan lembur terkirim ke atasan Anda.',
            'data' => Present::overtime($overtime->fresh()),
        ], 201);
    }

    public function destroy(OvertimeApproval $overtime): JsonResponse
    {
        abort_unless(
            $overtime->employee_id === $this->employee()->id && $overtime->status === OvertimeApproval::STATUS_PENDING,
            403,
            'Hanya pengajuan yang masih menunggu yang bisa dibatalkan.',
        );

        $this->workflow->cancel($overtime);

        return $this->message('Pengajuan lembur dibatalkan.');
    }
}
