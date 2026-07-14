<?php

namespace App\Http\Controllers;

use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use App\Services\PunchIngestionService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PunchController extends Controller
{
    public function __construct(private readonly PunchIngestionService $ingestion)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'all';
        $perPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $scope = DataScope::forAttendance($request->user());

        $punches = AttendancePunch::query()
            ->with(['device', 'employee'])
            // Punches of employees outside the scope stay hidden; unmatched punches
            // (no employee yet) remain visible — that is exactly what needs enrolling.
            ->when(! $scope->isUnrestricted(), fn ($query) => $query->where(
                fn ($q) => $q->whereNull('employee_id')->orWhereIn('employee_id', $scope->employees()->select('id')),
            ))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($request->integer('device_id'), fn ($query, $id) => $query->where('device_id', $id))
            ->latest('punched_at')
            ->paginate($perPage)
            ->withQueryString();

        // Distinct unmatched PINs surface at the top for quick enrollment.
        $unmatchedPins = AttendancePunch::query()
            ->unmatched()
            ->selectRaw('machine_user_id, device_id, count(*) as total, max(punched_at) as last_seen')
            ->groupBy('machine_user_id', 'device_id')
            ->with('device')
            ->get();

        return view('attendance.punches.index', [
            'punches' => $punches,
            'unmatchedPins' => $unmatchedPins,
            'devices' => Device::query()->orderBy('name')->get(),
            'employees' => $scope->employees()->active()->orderBy('full_name')->get(),
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'machine_user_id' => ['required', 'string', 'max:50'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        DataScope::forAttendance($request->user())->authorize($employee);

        $this->ingestion->assignPin(
            $employee,
            $data['device_id'] ? Device::find($data['device_id']) : null,
            $data['machine_user_id'],
        );

        return redirect()->route('attendance.punches.index')->with('status', 'PIN dipetakan & absensi terkait dihitung ulang.');
    }

    public function ignore(Request $request, AttendancePunch $punch): RedirectResponse
    {
        // An unmatched punch belongs to nobody yet, so anyone reviewing the log may
        // ignore it; a matched one only by whoever may see that employee.
        if ($punch->employee_id) {
            DataScope::forAttendance($request->user())->authorize($punch->employee);
        }

        $punch->forceFill(['status' => AttendancePunch::STATUS_IGNORED])->save();

        return redirect()->back()->with('status', 'Punch ditandai diabaikan.');
    }
}
