<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Support\ApprovalNotifier;
use Illuminate\Validation\ValidationException;

/**
 * Alur pengajuan lembur: karyawan mengajukan, atasan langsung memutuskan. HR boleh
 * mengambil alih keputusan (mis. atasan cuti panjang atau karyawan belum punya
 * atasan). Dipakai bersama oleh halaman web Lembur Saya dan API aplikasi HP, supaya
 * aturan durasi, satu-pengajuan-per-tanggal, dan notifikasinya tidak ditulis dua kali.
 */
class OvertimeWorkflow
{
    /**
     * @param  array{work_date:string, start_time:string, end_time:string, reason:string}  $data
     *
     * @throws ValidationException
     */
    public function submit(Employee $employee, array $data): OvertimeApproval
    {
        if (! $employee->manager_id) {
            throw ValidationException::withMessages([
                'work_date' => 'Atasan langsung Anda belum diatur. Hubungi HR sebelum mengajukan lembur.',
            ]);
        }

        $minutes = OvertimeApproval::minutesBetween($data['start_time'], $data['end_time']);

        if ($minutes <= 0) {
            throw ValidationException::withMessages(['end_time' => 'Jam selesai harus menghasilkan durasi lembur lebih dari 0 menit.']);
        }

        if ($minutes > OvertimeApproval::MAX_REQUEST_MINUTES) {
            $maxHours = intdiv(OvertimeApproval::MAX_REQUEST_MINUTES, 60);

            throw ValidationException::withMessages(['end_time' => "Durasi lembur tidak wajar (lebih dari {$maxHours} jam). Periksa kembali jam mulai & selesai."]);
        }

        // Satu baris per karyawan per tanggal (unique key di tabelnya), jadi tanggal
        // yang sudah punya pengajuan tidak boleh diinsert lagi.
        $existing = OvertimeApproval::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $data['work_date'])
            ->first();

        if ($existing && $existing->status !== OvertimeApproval::STATUS_REJECTED) {
            throw ValidationException::withMessages(['work_date' => 'Anda sudah mengajukan lembur untuk tanggal ini.']);
        }

        $computed = (int) (Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $data['work_date'])
            ->value('overtime_minutes') ?? 0);

        // Pengajuan yang pernah ditolak boleh diperbaiki dan diajukan ulang: barisnya
        // dipakai ulang (insert baru menabrak unique key) dan jejak keputusan lama
        // dibersihkan agar catatan penolakan sebelumnya tidak ikut terbawa.
        $overtime = OvertimeApproval::query()->updateOrCreate([
            'employee_id' => $employee->id,
            'work_date' => $data['work_date'],
        ], [
            'supervisor_id' => $employee->manager_id,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'requested_minutes' => $minutes,
            'reason' => $data['reason'],
            'requested_at' => now(),
            'computed_minutes' => $computed,
            'approved_minutes' => 0,
            'status' => OvertimeApproval::STATUS_PENDING,
            'reviewed_by' => null,
            'decided_at' => null,
            'notes' => null,
        ]);

        app(ApprovalNotifier::class)->overtimeRequested($overtime);

        return $overtime;
    }

    public function cancel(OvertimeApproval $overtime): void
    {
        // Atasan sedang menunggu keputusan: beri tahu sebelum datanya hilang.
        app(ApprovalNotifier::class)->overtimeCancelled($overtime);

        $overtime->delete();
    }

    public function approve(OvertimeApproval $overtime, User $actor, ?int $approvedMinutes = null, string $decidedBy = 'atasan'): void
    {
        $approved = $approvedMinutes !== null
            ? max(0, min($overtime->requested_minutes, $approvedMinutes))
            : $overtime->requested_minutes;

        $overtime->update([
            'status' => OvertimeApproval::STATUS_APPROVED,
            'approved_minutes' => $approved,
            'reviewed_by' => $actor->id,
            'decided_at' => now(),
        ]);

        app(ApprovalNotifier::class)->overtimeDecided($overtime, $decidedBy);
    }

    public function reject(OvertimeApproval $overtime, User $actor, ?string $notes = null, string $decidedBy = 'atasan'): void
    {
        $overtime->update([
            'status' => OvertimeApproval::STATUS_REJECTED,
            'approved_minutes' => 0,
            'reviewed_by' => $actor->id,
            'decided_at' => now(),
            'notes' => $notes,
        ]);

        app(ApprovalNotifier::class)->overtimeDecided($overtime, $decidedBy);
    }

    /** Lihat OvertimeApproval::isRevocable() untuk batas waktunya. */
    public function revoke(OvertimeApproval $overtime): void
    {
        $overtime->update([
            'status' => OvertimeApproval::STATUS_PENDING,
            'approved_minutes' => 0,
            'reviewed_by' => null,
            'decided_at' => null,
        ]);

        app(ApprovalNotifier::class)->overtimeApprovalRevoked($overtime);
    }
}
