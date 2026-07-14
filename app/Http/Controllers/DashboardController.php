<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\DataScope;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function __invoke(): View
    {
        $user = auth()->user();

        return view('dashboard', [
            'metrics' => $this->metrics($user),
            'todo' => $this->todo($user),
            'personal' => $user?->employee ? $this->personalData($user->employee) : null,
        ]);
    }

    /**
     * Angka ringkas — selalu dihitung dalam cakupan data si pengguna, supaya cocok
     * dengan isi daftarnya (HR cabang tidak melihat total se-perusahaan).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function metrics(?User $user): \Illuminate\Support\Collection
    {
        if (! $user) {
            return collect();
        }

        $scope = DataScope::forEmployees($user);

        return collect([
            [
                'label' => 'Karyawan Aktif',
                'value' => $scope->employees()->active()->count(),
                'tone' => 'emerald',
                'icon' => 'user-check',
                'permission' => 'employees.view',
                'route' => 'employees.index',
            ],
            [
                'label' => 'Kontrak Habis 30 Hari',
                'value' => EmployeeContract::query()
                    ->expiringWithin(30)
                    ->whereIn('employee_id', $scope->employees()->select('id'))
                    ->count(),
                'tone' => 'rose',
                'icon' => 'calendar-clock',
                'permission' => 'employees.view',
                'route' => 'employees.index',
            ],
        ])->filter(fn (array $metric) => $user->can($metric['permission']))->values();
    }

    /**
     * Antrean kerja HR: hanya yang benar-benar boleh diputuskan pengguna ini (per
     * menu) dan hanya untuk karyawan dalam cakupannya. Baris yang kosong tidak
     * ditampilkan, jadi dashboard hanya memuat hal yang perlu dikerjakan.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function todo(?User $user): \Illuminate\Support\Collection
    {
        if (! $user) {
            return collect();
        }

        $scope = DataScope::forAttendance($user);

        $pendingLeave = fn (): int => LeaveRequest::query()
            ->where('status', LeaveRequestStatus::PendingHr->value)
            ->tap(fn ($query) => $scope->constrain($query))
            ->count();

        $pendingCorrections = fn (): int => AttendanceCorrection::query()
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->tap(fn ($query) => $scope->constrain($query))
            ->count();

        $pendingSwaps = fn (): int => ShiftSwapRequest::query()
            ->where('status', ShiftSwapRequest::STATUS_PENDING_HR)
            ->tap(fn ($query) => $scope->constrain($query, 'requester_id'))
            ->tap(fn ($query) => $scope->constrain($query, 'partner_id'))
            ->count();

        // Karyawan aktif yang hari ini belum punya baris absensi sama sekali —
        // biasanya berarti hari itu belum diproses.
        $unprocessedToday = fn (): int => $scope->employees()
            ->active()
            ->whereDoesntHave('attendances', fn ($query) => $query->whereDate('work_date', now()->toDateString()))
            ->count();

        return collect([
            [
                'label' => 'Cuti/izin menunggu keputusan HR',
                'permission' => 'leave.update',
                'route' => 'attendance.leave.index',
                'count' => $pendingLeave,
                'tone' => 'amber',
            ],
            [
                'label' => 'Koreksi absensi menunggu keputusan',
                'permission' => 'corrections.update',
                'route' => 'attendance.corrections.index',
                'count' => $pendingCorrections,
                'tone' => 'amber',
            ],
            [
                'label' => 'Tukar jadwal menunggu keputusan HR',
                'permission' => 'swaps.update',
                'route' => 'attendance.swaps.index',
                'count' => $pendingSwaps,
                'tone' => 'amber',
            ],
            [
                'label' => 'Karyawan belum ada absensi hari ini',
                'permission' => 'attendance-daily.view',
                'route' => 'attendance.daily.index',
                'count' => $unprocessedToday,
                'tone' => 'sky',
            ],
        ])
            ->filter(fn (array $item) => $user->can($item['permission']))
            // Hitung hanya untuk baris yang memang boleh dilihat.
            ->map(fn (array $item) => [...$item, 'count' => ($item['count'])()])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values();
    }

    /**
     * Self-service snapshot for an employee: leave balances, upcoming schedule, and
     * how many requests are in flight or waiting on them as a supervisor.
     *
     * @return array<string, mixed>
     */
    private function personalData(Employee $employee): array
    {
        $year = (int) now()->year;
        $employee->loadMissing(['department', 'jobPosition']);

        return [
            'employee' => $employee,
            'balances' => LeaveType::query()
                ->where('is_active', true)
                ->where('counts_against_balance', true)
                ->orderBy('name')
                ->get()
                ->map(fn (LeaveType $type) => [
                    'name' => $type->name,
                    'remaining' => $this->balances->remaining($employee, $type, $year),
                ]),
            'schedule' => $employee->schedules()
                ->whereBetween('work_date', [now()->toDateString(), now()->addDays(6)->toDateString()])
                ->with('shift')
                ->orderBy('work_date')
                ->get(),
            'myPending' => LeaveRequest::query()->where('employee_id', $employee->id)
                ->whereIn('status', [LeaveRequestStatus::PendingSupervisor->value, LeaveRequestStatus::PendingHr->value])->count()
                + OvertimeApproval::query()->where('employee_id', $employee->id)->where('status', OvertimeApproval::STATUS_PENDING)->count()
                + $employee->swapRequests()->whereIn('status', [ShiftSwapRequest::STATUS_PENDING_PARTNER, ShiftSwapRequest::STATUS_PENDING_HR])->count()
                + $employee->attendanceCorrections()->where('status', AttendanceCorrection::STATUS_PENDING)->count(),
            'needApproval' => LeaveRequest::query()->where('supervisor_id', $employee->id)->where('status', LeaveRequestStatus::PendingSupervisor->value)->count()
                + OvertimeApproval::query()->where('supervisor_id', $employee->id)->where('status', OvertimeApproval::STATUS_PENDING)->count()
                + $employee->swapRequestsAsPartner()->where('status', ShiftSwapRequest::STATUS_PENDING_PARTNER)->count(),
        ];
    }
}
