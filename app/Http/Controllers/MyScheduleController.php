<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftSwapRequest;
use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use App\Support\LeaveCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MyScheduleController extends Controller
{
    public function __construct(private readonly ShiftSwapService $swaps) {}

    public function index(): View
    {
        $employee = $this->employee();

        $windowStart = now()->startOfDay();
        $windowEnd = now()->addDays(14)->startOfDay();

        return view('attendance.my-schedule.index', [
            'employee' => $employee,
            'schedule' => $employee->schedules()
                ->whereDate('work_date', '>=', $windowStart->toDateString())
                ->whereDate('work_date', '<=', $windowEnd->toDateString())
                ->with('shift')
                ->orderBy('work_date')
                ->get(),
            // Cuti/izin yang menyentuh window 14 hari, dari sumber yang sama dengan
            // halaman "Jadwal Saya" agar hari yang sama tidak tampil berbeda.
            'calendar' => LeaveCalendar::for($employee, $windowStart, $windowEnd),
            'myRequests' => $employee->swapRequests()
                ->with(['partner', 'reviewer'])
                ->latest('id')
                ->get(),
            'pendingForMe' => $employee->swapRequestsAsPartner()
                ->where('status', ShiftSwapRequest::STATUS_PENDING_PARTNER)
                ->with('requester')
                ->latest('id')
                ->get(),
            // Tukar shift hanya antar rekan selokasi & berbagi minimal satu divisi.
            'colleagues' => Employee::query()
                ->active()
                ->where('id', '!=', $employee->id)
                ->when($employee->branch_id, fn ($query) => $query->where('branch_id', $employee->branch_id))
                ->when(
                    $employee->departmentIds() !== [],
                    fn ($query) => $query->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $employee->departmentIds())),
                )
                ->orderBy('full_name')
                ->get(),
            'types' => ShiftSwapRequest::typeLabels(),
        ]);
    }

    public function store(StoreShiftSwapRequest $request): RedirectResponse
    {
        $this->swaps->submit($this->employee(), $request->validated());

        return redirect()->route('my-schedule.index')->with('status', 'Permintaan tukar jadwal terkirim ke rekan.');
    }

    public function respond(ShiftSwapRequest $swap): RedirectResponse
    {
        abort_unless($swap->partner_id === $this->employee()->id && $swap->isPendingPartner(), 403);

        $accept = request()->string('decision')->toString() === 'accept';
        $this->swaps->partnerRespond($swap, $accept);

        return redirect()->route('my-schedule.index')->with('status', $accept ? 'Anda menyetujui, diteruskan ke HR.' : 'Anda menolak permintaan tukar.');
    }

    public function cancel(ShiftSwapRequest $swap): RedirectResponse
    {
        abort_unless($swap->requester_id === $this->employee()->id && ($swap->isPendingPartner() || $swap->isPendingHr()), 403);

        $this->swaps->cancel($swap);

        return redirect()->route('my-schedule.index')->with('status', 'Permintaan tukar dibatalkan.');
    }

    private function employee(): Employee
    {
        $employee = auth()->user()->employee;

        abort_unless($employee, 403, 'Akun Anda belum tertaut ke data karyawan.');

        return $employee;
    }
}
