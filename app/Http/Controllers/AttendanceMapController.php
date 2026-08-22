<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Services\AttendanceResolver;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Peta absen mandiri: memplot karyawan WFH / dinas luar pada satu tanggal
 * berdasarkan koordinat yang terekam saat mereka absen masuk, ditambah daftar
 * siapa yang seharusnya absen mandiri hari itu tetapi belum melakukannya.
 */
class AttendanceMapController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->input('date'));
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $search = $request->string('search')->toString() ?: null;
        $scope = DataScope::forAttendance($request->user());

        $employees = $scope->employees()
            ->active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->byDepartment($departmentId)
            ->when($search, fn ($query, $s) => $query->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$s}%")
                ->orWhere('employee_number', 'like', "%{$s}%")))
            ->with(['branch', 'jobPosition'])
            ->orderBy('full_name')
            ->get();

        $remoteEmployees = $employees->whereIn('id', $this->remoteEmployeeIds($employees, $date));

        $attendances = Attendance::query()
            ->whereIn('employee_id', $remoteEmployees->modelKeys())
            ->whereDate('work_date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        // Titik peta hanya untuk yang koordinat absen masuknya lengkap; sisanya masuk
        // daftar "belum absen" yang justru perlu ditindaklanjuti HR.
        [$plotted, $pending] = $remoteEmployees->partition(
            fn (Employee $e) => $attendances->get($e->id)?->clock_in_latitude !== null
                && $attendances->get($e->id)?->clock_in_longitude !== null,
        );

        return view('attendance.map.index', [
            'points' => $plotted->map(fn (Employee $e) => $this->point($e, $attendances->get($e->id)))->values(),
            'pending' => $pending->map(fn (Employee $e) => [
                'employee' => $e,
                'attendance' => $attendances->get($e->id),
            ])->values(),
            'date' => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
            'search' => $search,
            'hasNoScope' => $scope->isEmpty(),
        ]);
    }

    /**
     * Karyawan yang hari itu bekerja jarak jauh, dari tiga sumber sekaligus: absensi
     * yang sudah ter-resolve, hari WFH di roster, dan pengajuan WFH/dinas luar yang
     * disetujui. Sumber kedua & ketiga dipakai agar yang belum absen tetap terdaftar
     * meski barisnya belum dibuat resolver.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return list<int>
     */
    private function remoteEmployeeIds($employees, Carbon $date): array
    {
        $ids = $employees->modelKeys();

        if ($ids === []) {
            return [];
        }

        $statuses = array_map(fn ($status) => $status->value, AttendanceResolver::REMOTE_STATUSES);
        $day = $date->toDateString();

        $fromAttendance = Attendance::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('work_date', $day)
            ->whereIn('status', $statuses)
            ->pluck('employee_id');

        $fromRoster = EmployeeSchedule::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('work_date', $day)
            ->where('is_wfh', true)
            ->pluck('employee_id');

        $fromLeave = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->approvedOn($day)
            ->whereHas('leaveType', fn ($query) => $query->whereIn('attendance_status', $statuses))
            ->pluck('employee_id');

        return $fromAttendance->merge($fromRoster)->merge($fromLeave)->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function point(Employee $employee, Attendance $attendance): array
    {
        return [
            'name' => $employee->full_name,
            'number' => $employee->employee_number,
            'position' => $employee->jobPosition?->name,
            'branch' => $employee->branch?->name,
            'status' => $attendance->status->value,
            'status_label' => $attendance->status->label(),
            'lat' => (float) $attendance->clock_in_latitude,
            'lng' => (float) $attendance->clock_in_longitude,
            'accuracy' => $attendance->clock_in_accuracy_m,
            'clock_in' => $attendance->clock_in_label,
            'clock_out' => $attendance->clock_out_label,
            'photo_url' => $attendance->selfieFor('in')['photo_url'] ?? null,
            'history_url' => route('attendance.daily.history', $employee),
        ];
    }

    private function resolveDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
