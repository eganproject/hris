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
    /**
     * Who counts as "HR" depends on the menu the request lives in: only the people
     * allowed to decide THAT kind of request are notified about it.
     */
    public const LEAVE_APPROVER = 'leave.update';

    public const CORRECTION_APPROVER = 'corrections.update';

    public const SWAP_APPROVER = 'swaps.update';

    public const CONTRACT_MANAGER = 'employees.update';

    public const DEVICE_MANAGER = 'devices.view';

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

    /** "Cuti Tahunan" / "Izin" / "Sakit" — jenis yang diajukan, bukan selalu "cuti". */
    private function leaveType(LeaveRequest $leave): string
    {
        return $leave->leaveType?->name ?? 'Cuti/Izin';
    }

    /** "10 – 12 Feb 2026 (3 hari)" — periode lengkap dengan tahun & jumlah harinya. */
    private function leavePeriod(LeaveRequest $leave): string
    {
        $start = $leave->start_date;
        $end = $leave->end_date;

        $range = $start->isSameDay($end)
            ? $start->translatedFormat('d M Y')
            : ($start->isSameMonth($end)
                ? $start->translatedFormat('d').' – '.$end->translatedFormat('d M Y')
                : $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'));

        return $range.' ('.$leave->days.' hari)';
    }

    public function leaveSubmitted(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'supervisor', 'leaveType');

        $who = $leave->employee?->full_name;
        $message = $who.' mengajukan '.$this->leaveType($leave).' — '.$this->leavePeriod($leave).'.'
            .($leave->reason ? ' Alasan: '.$leave->reason : '');

        // With a supervisor the request waits for them; otherwise it goes straight to HR.
        if ($leave->supervisor) {
            $this->toEmployee($leave->supervisor, new ApprovalNotification(
                title: 'Pengajuan '.$this->leaveType($leave).' baru',
                message: $message,
                url: route('my-leave.index'),
                category: 'leave',
            ));

            return;
        }

        $this->toPermission(self::LEAVE_APPROVER, new ApprovalNotification(
            title: 'Pengajuan '.$this->leaveType($leave).' menunggu HR',
            message: $message.' Karyawan ini belum punya atasan, jadi langsung ke HR.',
            url: route('attendance.leave.index'),
            category: 'leave',
        ), $leave->employee);
    }

    /**
     * Diajukan HR untuk karyawan lain: yang bersangkutan perlu tahu ada pengajuan
     * atas namanya, karena bukan dia yang membuatnya.
     */
    public function leaveFiledForEmployee(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'leaveType');

        $this->toEmployee($leave->employee, new ApprovalNotification(
            title: 'Pengajuan '.$this->leaveType($leave).' dibuat untuk Anda',
            message: 'HR membuat pengajuan '.$this->leaveType($leave).' atas nama Anda — '.$this->leavePeriod($leave).'.',
            url: route('my-leave.index'),
            category: 'leave',
        ));
    }

    public function leavePendingHr(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee', 'leaveType', 'supervisorApprover');

        $approver = $leave->supervisorApprover?->name;

        $this->toPermission(self::LEAVE_APPROVER, new ApprovalNotification(
            title: $this->leaveType($leave).' menunggu persetujuan HR',
            message: $leave->employee?->full_name.' — '.$this->leaveType($leave).' '.$this->leavePeriod($leave).'.'
                .' Sudah disetujui atasan'.($approver ? ' ('.$approver.')' : '').', menunggu keputusan HR.',
            url: route('attendance.leave.index'),
            category: 'leave',
        ), $leave->employee);
    }

    public function correctionSubmitted(AttendanceCorrection $correction): void
    {
        $correction->loadMissing('employee');

        $this->toPermission(self::CORRECTION_APPROVER, new ApprovalNotification(
            title: 'Koreksi absensi baru',
            message: $correction->employee?->full_name.' mengajukan koreksi absensi '.$correction->work_date->translatedFormat('D, d M').'.',
            url: route('attendance.corrections.index'),
            category: 'correction',
        ), $correction->employee);
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

        $this->toPermission(self::SWAP_APPROVER, new ApprovalNotification(
            title: 'Tukar jadwal menunggu HR',
            message: $swap->requester?->full_name.' ⇄ '.$swap->partner?->full_name.' — rekan setuju, menunggu HR.',
            url: route('attendance.swaps.index'),
            category: 'swap',
        ), $swap->requester);
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

    /**
     * @param  string|null  $decidedBy  "atasan" atau "HR" — tanpa ini karyawan tidak tahu
     *                                  di tahap mana pengajuannya ditolak/disetujui.
     */
    public function leaveDecided(LeaveRequest $leave, ?string $decidedBy = null): void
    {
        $leave->loadMissing('employee', 'leaveType');

        $approved = $leave->status === LeaveRequestStatus::Approved;
        $type = $this->leaveType($leave);
        $by = $decidedBy ? ' oleh '.$decidedBy : '';

        $this->toEmployee($leave->employee, new ApprovalNotification(
            title: $type.($approved ? ' disetujui' : ' ditolak'),
            message: 'Pengajuan '.$type.' Anda — '.$this->leavePeriod($leave).' — '
                .($approved ? 'disetujui' : 'ditolak').$by.'.'
                .($leave->decision_notes ? ' Catatan: '.$leave->decision_notes : ''),
            url: route('my-leave.index'),
            category: 'leave',
        ));
    }

    /**
     * Dibatalkan: bisa oleh karyawannya sendiri (pengajuan yang masih menunggu) atau
     * oleh HR (cuti yang sudah disetujui — absensinya ikut dikembalikan). Pihak lain
     * yang terlibat harus tahu; pelakunya sendiri tidak dinotifikasi.
     */
    public function leaveCancelled(LeaveRequest $leave, bool $wasApproved): void
    {
        $leave->loadMissing('employee', 'leaveType', 'supervisor');

        $type = $this->leaveType($leave);
        $period = $this->leavePeriod($leave);

        $this->toEmployee($leave->employee, new ApprovalNotification(
            title: $type.' dibatalkan',
            message: $wasApproved
                ? $type.' Anda yang sudah disetujui — '.$period.' — dibatalkan oleh HR. Absensi pada hari tersebut dikembalikan.'
                : 'Pengajuan '.$type.' Anda — '.$period.' — dibatalkan.',
            url: route('my-leave.index'),
            category: 'leave',
        ));

        // Atasan yang masih menunggu keputusan perlu tahu permintaannya sudah gugur.
        $this->toEmployee($leave->supervisor, new ApprovalNotification(
            title: 'Pengajuan '.$type.' dibatalkan',
            message: ($leave->employee?->full_name ?? 'Karyawan').' membatalkan pengajuan '.$type.' — '.$period.'.',
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
        $this->toPermission(self::CONTRACT_MANAGER, new ApprovalNotification(
            title: 'Kontrak akan berakhir',
            message: 'Kontrak '.$contract->contract_number.' a.n. '.$employee->full_name.' berakhir dalam '.$daysLeft.' hari ('.$contract->end_date->translatedFormat('d M Y').'). Perpanjang atau proses keluar.',
            url: route('employees.show', $employee),
            category: 'contract',
        ), $employee);
    }

    public function contractAutoDeactivated(Employee $employee, EmployeeContract $contract): void
    {
        $this->toPermission(self::CONTRACT_MANAGER, new ApprovalNotification(
            title: 'Karyawan dinonaktifkan otomatis',
            message: $employee->full_name.' dinonaktifkan otomatis karena kontrak '.$contract->contract_number.' berakhir ('.$contract->end_date->translatedFormat('d M Y').').',
            url: route('employees.show', $employee),
            category: 'contract',
        ), $employee);
    }

    public function deviceOffline(Device $device, int $minutesOffline): void
    {
        $this->toPermission(self::DEVICE_MANAGER, new ApprovalNotification(
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

    /**
     * Notify every user that holds a given permission (excluding the actor). When the
     * notification is about a specific employee, only those whose data scope covers
     * that employee are notified — an HR cabang must not be told about, and linked to,
     * a request they are not allowed to open.
     */
    private function toPermission(string $permission, ApprovalNotification $notification, ?Employee $about = null): void
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

        if ($about) {
            $bypass = str_starts_with($permission, 'employees.')
                ? User::SCOPE_BYPASS_EMPLOYEES
                : User::SCOPE_BYPASS_ATTENDANCE;

            $recipients = $recipients->filter(fn (User $user) => $about->isVisibleTo($user, $bypass));
        }

        $recipients->each->notify($notification);
    }

    private function hm(int $minutes): string
    {
        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }
}
