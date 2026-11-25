<?php

namespace App\Support\Mobile;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\ShiftSwapRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Bentuk JSON bersama untuk API aplikasi HP: satu tempat yang menentukan bagaimana
 * karyawan, pengajuan, dan status tampil, supaya semua layar memakai istilah sama.
 */
final class Present
{
    /** @return array<string, mixed>|null */
    public static function person(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => self::initials($employee->full_name),
            'job_title' => $employee->jobPosition?->name,
            'department' => $employee->department?->name,
            'photo_url' => $employee->photo_url,
        ];
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];

        return Str::upper(collect($words)->filter()->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('')) ?: '?';
    }

    public static function date(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }

    /** Kategori tampilan aplikasi: cuti / izin / sakit, dari status absensi jenisnya. */
    public static function leaveCategory(?LeaveType $type): string
    {
        return match ($type?->attendance_status) {
            AttendanceStatus::Sick => 'sakit',
            AttendanceStatus::Permit => 'izin',
            AttendanceStatus::Wfh, AttendanceStatus::BusinessTrip => 'izin',
            default => 'cuti',
        };
    }

    /** @return array<string, mixed> */
    public static function leave(LeaveRequest $leave): array
    {
        $leave->loadMissing('leaveType', 'supervisor');

        return [
            'kind' => 'leave',
            'id' => $leave->id,
            'category' => self::leaveCategory($leave->leaveType),
            'title' => ($leave->leaveType?->name ?? 'Cuti').' · '.$leave->days.' hari',
            'leave_type' => $leave->leaveType ? ['id' => $leave->leaveType->id, 'name' => $leave->leaveType->name] : null,
            'start_date' => self::date($leave->start_date),
            'end_date' => self::date($leave->end_date),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'tone' => $leave->status->tone(),
            'decision_notes' => $leave->decision_notes,
            'has_attachment' => $leave->hasAttachment(),
            'supervisor' => $leave->supervisor?->full_name,
            'can_cancel' => $leave->status->isPending(),
            'submitted_at' => $leave->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function overtime(OvertimeApproval $overtime): array
    {
        $hours = rtrim(rtrim(number_format($overtime->requested_minutes / 60, 1, ',', ''), '0'), ',');

        return [
            'kind' => 'overtime',
            'id' => $overtime->id,
            'category' => 'lembur',
            'title' => 'Lembur · '.$hours.' jam',
            'work_date' => self::date($overtime->work_date),
            'start_time' => $overtime->start_time ? substr($overtime->start_time, 0, 5) : null,
            'end_time' => $overtime->end_time ? substr($overtime->end_time, 0, 5) : null,
            'requested_minutes' => $overtime->requested_minutes,
            'approved_minutes' => $overtime->approved_minutes,
            'reason' => $overtime->reason,
            'status' => $overtime->status,
            'status_label' => $overtime->status_label,
            'tone' => $overtime->status_tone,
            'notes' => $overtime->notes,
            'can_cancel' => $overtime->status === OvertimeApproval::STATUS_PENDING,
            'submitted_at' => ($overtime->requested_at ?? $overtime->created_at)?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function swap(ShiftSwapRequest $swap, ?Employee $viewer = null): array
    {
        $swap->loadMissing('requester', 'partner');

        $role = $viewer && $swap->partner_id === $viewer->id ? 'partner' : 'requester';

        return [
            'kind' => 'swap',
            'id' => $swap->id,
            'category' => 'tukar',
            'title' => $swap->type_label.' dengan '.($role === 'partner' ? $swap->requester?->full_name : $swap->partner?->full_name),
            'type' => $swap->type,
            'type_label' => $swap->type_label,
            'role' => $role,
            'requester' => self::person($swap->requester),
            'partner' => self::person($swap->partner),
            'requester_date' => self::date($swap->requester_date),
            'partner_date' => self::date($swap->partner_date),
            'reason' => $swap->reason,
            'status' => $swap->status,
            'status_label' => $swap->status_label,
            'tone' => $swap->status_tone,
            'can_cancel' => $role === 'requester' && ($swap->isPendingPartner() || $swap->isPendingHr()),
            'can_respond' => $role === 'partner' && $swap->isPendingPartner(),
            'submitted_at' => $swap->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function correction(AttendanceCorrection $correction): array
    {
        return [
            'kind' => 'correction',
            'id' => $correction->id,
            'category' => 'koreksi',
            'title' => 'Koreksi absensi',
            'work_date' => self::date($correction->work_date),
            'requested_clock_in' => $correction->requested_clock_in ? substr((string) $correction->requested_clock_in, 0, 5) : null,
            'requested_clock_out' => $correction->requested_clock_out ? substr((string) $correction->requested_clock_out, 0, 5) : null,
            'reason' => $correction->reason,
            'status' => $correction->status,
            'status_label' => $correction->status_label,
            'tone' => $correction->status_tone,
            'decision_notes' => $correction->decision_notes,
            'can_cancel' => false,
            'submitted_at' => $correction->created_at?->toIso8601String(),
        ];
    }
}
