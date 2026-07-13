<?php

namespace App\Support;

use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Notifications\ApprovalNotification;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Sends in-app notifications to the people who need to act on a request: the
 * employee's supervisor, the swap partner, or the HR team (users allowed to make
 * attendance decisions). The person who triggered the event is never notified.
 */
class ApprovalNotifier
{
    /** HR-level approvers = users allowed to decide attendance matters. */
    public const HR_PERMISSION = 'attendance.update';

    public function overtimeRequested(OvertimeApproval $overtime): void
    {
        $overtime->loadMissing('employee', 'supervisor');

        $this->toEmployee($overtime->supervisor, new ApprovalNotification(
            title: 'Pengajuan lembur baru',
            message: $overtime->employee?->full_name.' mengajukan lembur '.$overtime->work_date->translatedFormat('D, d M').' ('.$this->hm($overtime->requested_minutes).').',
            url: route('my-overtime.index'),
            category: 'overtime',
        ));
    }

    public function leaveSubmitted(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'supervisor', 'leaveType');

        $range = $leave->start_date->translatedFormat('d M').' – '.$leave->end_date->translatedFormat('d M');
        $who = $leave->employee?->full_name;

        // With a supervisor the request waits for them; otherwise it goes straight to HR.
        if ($leave->supervisor) {
            $this->toEmployee($leave->supervisor, new ApprovalNotification(
                title: 'Pengajuan cuti baru',
                message: $who.' mengajukan '.($leave->leaveType?->name ?? 'cuti').' ('.$range.').',
                url: route('my-leave.index'),
                category: 'leave',
            ));

            return;
        }

        $this->toHr(new ApprovalNotification(
            title: 'Pengajuan cuti menunggu HR',
            message: $who.' mengajukan '.($leave->leaveType?->name ?? 'cuti').' ('.$range.').',
            url: route('attendance.leave.index'),
            category: 'leave',
        ));
    }

    public function leavePendingHr(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'leaveType');

        $this->toHr(new ApprovalNotification(
            title: 'Cuti menunggu persetujuan HR',
            message: $leave->employee?->full_name.' — disetujui atasan, menunggu keputusan HR.',
            url: route('attendance.leave.index'),
            category: 'leave',
        ));
    }

    public function correctionSubmitted(AttendanceCorrection $correction): void
    {
        $correction->loadMissing('employee');

        $this->toHr(new ApprovalNotification(
            title: 'Koreksi absensi baru',
            message: $correction->employee?->full_name.' mengajukan koreksi absensi '.$correction->work_date->translatedFormat('D, d M').'.',
            url: route('attendance.corrections.index'),
            category: 'correction',
        ));
    }

    public function swapRequested(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing('requester', 'partner');

        $this->toEmployee($swap->partner, new ApprovalNotification(
            title: 'Permintaan tukar jadwal',
            message: $swap->requester?->full_name.' mengajukan '.$swap->type_label.' ('.$swap->requester_date->translatedFormat('D, d M').').',
            url: route('my-schedule.index'),
            category: 'swap',
        ));
    }

    public function swapPendingHr(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing('requester', 'partner');

        $this->toHr(new ApprovalNotification(
            title: 'Tukar jadwal menunggu HR',
            message: $swap->requester?->full_name.' ⇄ '.$swap->partner?->full_name.' — rekan setuju, menunggu HR.',
            url: route('attendance.swaps.index'),
            category: 'swap',
        ));
    }

    // --- Result notifications back to the employee who submitted the request ---

    public function overtimeDecided(OvertimeApproval $overtime): void
    {
        $overtime->loadMissing('employee');
        $date = $overtime->work_date->translatedFormat('D, d M');

        $message = $overtime->status === OvertimeApproval::STATUS_APPROVED
            ? 'Lembur Anda '.$date.' disetujui atasan ('.$this->hm($overtime->approved_minutes).').'
            : 'Lembur Anda '.$date.' ditolak atasan.'.($overtime->notes ? ' Catatan: '.$overtime->notes : '');

        $this->toEmployee($overtime->employee, new ApprovalNotification(
            title: $overtime->status === OvertimeApproval::STATUS_APPROVED ? 'Lembur disetujui' : 'Lembur ditolak',
            message: $message,
            url: route('my-overtime.index'),
            category: 'overtime',
        ));
    }

    public function leaveDecided(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'leaveType');
        $range = $leave->start_date->translatedFormat('d M').' – '.$leave->end_date->translatedFormat('d M');
        $approved = $leave->status === LeaveRequestStatus::Approved;

        $this->toEmployee($leave->employee, new ApprovalNotification(
            title: $approved ? 'Cuti disetujui' : 'Cuti ditolak',
            message: ($approved
                ? 'Pengajuan '.($leave->leaveType?->name ?? 'cuti').' Anda ('.$range.') telah disetujui.'
                : 'Pengajuan '.($leave->leaveType?->name ?? 'cuti').' Anda ('.$range.') ditolak.').($leave->decision_notes ? ' Catatan: '.$leave->decision_notes : ''),
            url: route('my-leave.index'),
            category: 'leave',
        ));
    }

    public function correctionDecided(AttendanceCorrection $correction): void
    {
        $correction->loadMissing('employee');
        $approved = $correction->status === AttendanceCorrection::STATUS_APPROVED;
        $date = $correction->work_date->translatedFormat('D, d M');

        $this->toEmployee($correction->employee, new ApprovalNotification(
            title: $approved ? 'Koreksi absensi disetujui' : 'Koreksi absensi ditolak',
            message: ($approved
                ? 'Koreksi absensi Anda '.$date.' disetujui & absensi diperbarui.'
                : 'Koreksi absensi Anda '.$date.' ditolak.').($correction->decision_notes ? ' Catatan: '.$correction->decision_notes : ''),
            url: route('my-attendance.index'),
            category: 'correction',
        ));
    }

    public function swapRejectedByPartner(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing('requester', 'partner');

        $this->toEmployee($swap->requester, new ApprovalNotification(
            title: 'Tukar jadwal ditolak',
            message: ($swap->partner?->full_name ?? 'Rekan').' menolak permintaan tukar jadwal Anda.',
            url: route('my-schedule.index'),
            category: 'swap',
        ));
    }

    public function swapDecidedByHr(ShiftSwapRequest $swap): void
    {
        $swap->loadMissing('requester', 'partner');
        $approved = $swap->status === ShiftSwapRequest::STATUS_APPROVED;

        $this->toEmployee($swap->requester, new ApprovalNotification(
            title: $approved ? 'Tukar jadwal disetujui' : 'Tukar jadwal ditolak',
            message: $approved
                ? 'Tukar jadwal Anda dengan '.($swap->partner?->full_name ?? 'rekan').' disetujui HR. Jadwal telah diperbarui.'
                : 'Tukar jadwal Anda ditolak HR.'.($swap->decision_notes ? ' Catatan: '.$swap->decision_notes : ''),
            url: route('my-schedule.index'),
            category: 'swap',
        ));

        // The partner's schedule changed too, so let them know on approval.
        if ($approved) {
            $this->toEmployee($swap->partner, new ApprovalNotification(
                title: 'Tukar jadwal disetujui',
                message: 'Tukar jadwal dengan '.($swap->requester?->full_name ?? 'rekan').' disetujui HR. Jadwal Anda telah diperbarui.',
                url: route('my-schedule.index'),
                category: 'swap',
            ));
        }
    }

    // --- HR / admin operational notifications ---

    public function contractExpiring(Employee $employee, EmployeeContract $contract, int $daysLeft): void
    {
        $this->toPermission('employees.update', new ApprovalNotification(
            title: 'Kontrak akan berakhir',
            message: 'Kontrak '.$contract->contract_number.' a.n. '.$employee->full_name.' berakhir dalam '.$daysLeft.' hari ('.$contract->end_date->translatedFormat('d M Y').'). Perpanjang atau proses keluar.',
            url: route('employees.show', $employee),
            category: 'contract',
        ));
    }

    public function contractAutoDeactivated(Employee $employee, EmployeeContract $contract): void
    {
        $this->toPermission('employees.update', new ApprovalNotification(
            title: 'Karyawan dinonaktifkan otomatis',
            message: $employee->full_name.' dinonaktifkan otomatis karena kontrak '.$contract->contract_number.' berakhir ('.$contract->end_date->translatedFormat('d M Y').').',
            url: route('employees.show', $employee),
            category: 'contract',
        ));
    }

    public function deviceOffline(Device $device, int $minutesOffline): void
    {
        $this->toPermission(self::HR_PERMISSION, new ApprovalNotification(
            title: 'Mesin absensi offline',
            message: 'Mesin "'.$device->name.'" ('.$device->serial_number.') tidak mengirim data sejak '.($device->last_seen_at?->translatedFormat('d M H:i') ?? 'lama').' — sekitar '.$minutesOffline.' menit. Periksa koneksi perangkat.',
            url: route('attendance.devices.monitor'),
            category: 'device',
        ));
    }

    private function toEmployee(?Employee $employee, ApprovalNotification $notification): void
    {
        $user = $employee?->user;

        if ($user && $user->id !== Auth::id()) {
            $user->notify($notification);
        }
    }

    private function toHr(ApprovalNotification $notification): void
    {
        $this->toPermission(self::HR_PERMISSION, $notification);
    }

    /** Notify every user that holds a given permission (excluding the actor). */
    private function toPermission(string $permission, ApprovalNotification $notification): void
    {
        try {
            $recipients = User::query()
                ->permission($permission)
                ->when(Auth::id(), fn ($query) => $query->where('id', '!=', Auth::id()))
                ->get();
        } catch (PermissionDoesNotExist) {
            // The permission is not registered (yet), so nobody can hold it and there
            // is nobody to notify. Never let that abort the action being notified.
            return;
        }

        $recipients->each->notify($notification);
    }

    private function hm(int $minutes): string
    {
        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }
}
