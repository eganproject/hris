<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Http\Requests\StoreShiftSwapRequest;
use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tukar jadwal dari aplikasi HP. Validasi & penerapannya memakai StoreShiftSwapRequest
 * dan ShiftSwapService yang sama dengan halaman web Tukar Jadwal Saya.
 */
class SwapController extends HrController
{
    public function __construct(private readonly ShiftSwapService $swaps) {}

    /** ?tab=berjalan (default) | riwayat */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee();
        $history = $request->input('tab') === 'riwayat';

        $page = ShiftSwapRequest::query()->involving($employee->id)
            ->when(
                $history,
                fn ($q) => $q->whereNotIn('status', ShiftSwapRequest::ACTIVE_STATUSES),
                fn ($q) => $q->whereIn('status', ShiftSwapRequest::ACTIVE_STATUSES),
            )
            ->with(['requester.jobPosition', 'partner.jobPosition'])
            ->latest('id')
            ->paginate(20);

        return $this->paginated($page, fn (ShiftSwapRequest $s) => Present::swap($s, $employee));
    }

    /** Rekan yang boleh diajak tukar: selokasi & berbagi minimal satu divisi (sama dengan web). */
    public function colleagues(): JsonResponse
    {
        $employee = $this->employee();

        $colleagues = Employee::query()
            ->active()
            ->where('id', '!=', $employee->id)
            ->when($employee->branch_id, fn ($query) => $query->where('branch_id', $employee->branch_id))
            ->when(
                $employee->departmentIds() !== [],
                fn ($query) => $query->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $employee->departmentIds())),
            )
            ->with(['jobPosition', 'department'])
            ->orderBy('full_name')
            ->get();

        return $this->ok(
            $colleagues->map(fn (Employee $e) => Present::person($e))->values(),
            200,
            ['meta' => ['types' => ShiftSwapRequest::typeLabels(), 'attachment_max_mb' => ShiftSwapRequest::ATTACHMENT_MAX_MB]],
        );
    }

    public function store(StoreShiftSwapRequest $request): JsonResponse
    {
        $employee = $this->employee();
        $swap = $this->swaps->submit($employee, $request->validated());

        return response()->json([
            'message' => 'Permintaan tukar jadwal terkirim ke rekan.',
            'data' => Present::swap($swap->fresh(), $employee),
        ], 201);
    }

    /** Rekan menjawab: decision=accept|reject. */
    public function respond(Request $request, ShiftSwapRequest $swap): JsonResponse
    {
        $employee = $this->employee();
        abort_unless($swap->partner_id === $employee->id && $swap->isPendingPartner(), 403, 'Permintaan ini sudah tidak menunggu jawaban Anda.');

        $data = $request->validate(['decision' => ['required', 'in:accept,reject']]);
        $accept = $data['decision'] === 'accept';

        $this->swaps->partnerRespond($swap, $accept);

        return $this->message(
            $accept ? 'Anda menyetujui, diteruskan ke HR.' : 'Anda menolak permintaan tukar.',
            Present::swap($swap->fresh(), $employee),
        );
    }

    public function destroy(ShiftSwapRequest $swap): JsonResponse
    {
        $employee = $this->employee();
        abort_unless($swap->requester_id === $employee->id && ($swap->isPendingPartner() || $swap->isPendingHr()), 403, 'Permintaan ini sudah tidak bisa dibatalkan.');

        $this->swaps->cancel($swap);

        return $this->message('Permintaan tukar dibatalkan.');
    }
}
