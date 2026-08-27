<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use App\Support\ApprovalNotifier;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan cuti satu tahap: karyawan → atasan langsung, dan keputusan atasan
 * bersifat final. HR tidak lagi menjadi tahap kedua.
 *
 * HR tetap bisa memutuskan, tapi sebagai jalan keluar, bukan tahap: karyawan yang
 * belum punya atasan tidak akan pernah punya yang bisa menyetujui, dan pengajuan
 * lama yang terlanjur berhenti di antrean HR tetap harus bisa diselesaikan.
 */
class LeaveWorkflow
{
    public function __construct(private readonly AttendanceResolver $resolver) {}

    /**
     * Membuat pengajuan baru. Lampiran (bila ada) ikut disimpan di sini, bukan di
     * masing-masing controller, supaya pengajuan dari karyawan dan yang dibuatkan HR
     * memperlakukan berkasnya persis sama.
     *
     * @param  array{leave_type_id:int, start_date:string, end_date:string, reason?:?string, attachment?:?UploadedFile}  $data
     */
    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $employee->loadMissing('manager');

        $request = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'supervisor_id' => $employee->manager_id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => $employee->manager_id
                ? LeaveRequestStatus::PendingSupervisor
                : LeaveRequestStatus::PendingHr,
            ...$this->attachmentColumns($employee, $data['attachment'] ?? null),
        ]);

        app(ApprovalNotifier::class)->leaveSubmitted($request);

        return $request;
    }

    /**
     * Simpan lampiran ke disk privat dan kembalikan kolom-kolomnya. Berkas ditaruh
     * per karyawan agar mudah ditelusuri, dengan nama acak dari Laravel — nama asli
     * dari pengguna tidak pernah dipakai sebagai nama berkas di disk.
     *
     * @return array<string, mixed>
     */
    private function attachmentColumns(Employee $employee, ?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store("leave-attachments/{$employee->id}", LeaveRequest::ATTACHMENT_DISK),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => $attachment->getClientMimeType(),
            'attachment_size' => $attachment->getSize(),
        ];
    }

    /**
     * Setujui pengajuan — langsung final, tanpa tahap berikutnya.
     *
     * Siapa pun yang memutuskan (atasan atau HR sebagai jalan keluar) dicatat di
     * approved_by/decided_at. Kolom supervisor_* tidak lagi diisi: sejak alurnya satu
     * tahap, tidak ada lagi keputusan antara yang perlu dibedakan dari keputusan
     * akhir — kolomnya dipertahankan hanya untuk membaca pengajuan lama.
     */
    public function approve(LeaveRequest $request, User $actor): void
    {
        // Status cuti dan absensi hari-hari yang ditutupinya harus berubah bersama:
        // kalau syncAttendance() gagal di tengah, cutinya sudah berstatus "Disetujui"
        // tapi sebagian harinya masih terhitung Alfa di rekap kehadiran.
        DB::transaction(function () use ($request, $actor) {
            $request->update([
                'status' => LeaveRequestStatus::Approved,
                'approved_by' => $actor->id,
                'decided_at' => now(),
            ]);

            $this->syncAttendance($request);
        });

        app(ApprovalNotifier::class)->leaveDecided($request, $this->deciderLabel($request, $actor));
    }

    /**
     * Sebutan pengambil keputusan untuk notifikasi ke karyawan: "atasan" bila yang
     * memutuskan memang atasannya, selain itu "HR".
     */
    private function deciderLabel(LeaveRequest $request, User $actor): string
    {
        return $request->supervisor_id !== null && $request->supervisor_id === $actor->employee?->id
            ? 'atasan'
            : 'HR';
    }

    /**
     * Re-resolve the employee's attendance for each day the leave covers, so an
     * approved leave immediately shows as "Cuti" (and, when the leave is later
     * removed, reverts to the punch-based status). Only days up to today are
     * touched — future leave days are picked up by the nightly close-out on their
     * own date, so we don't create premature records.
     */
    public function syncAttendance(LeaveRequest $leave): void
    {
        $leave->loadMissing('employee');

        if (! $leave->employee || ! $leave->start_date || ! $leave->end_date) {
            return;
        }

        $today = Carbon::today();

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
            if ($date->greaterThan($today)) {
                continue;
            }

            $this->resolver->reprocess($leave->employee, $date);
        }
    }

    public function reject(LeaveRequest $request, User $actor, ?string $notes = null): void
    {
        $request->update([
            'status' => LeaveRequestStatus::Rejected,
            'decision_notes' => $notes,
            'approved_by' => $actor->id,
            'decided_at' => now(),
        ]);

        app(ApprovalNotifier::class)->leaveDecided($request, $this->deciderLabel($request, $actor));
    }

    /**
     * Cancelling keeps the request (and its decision trail) but takes it out of
     * effect. An already-approved leave also gives its days back to attendance, so
     * they revert from "Cuti" to the punch-based status.
     */
    public function cancel(LeaveRequest $request): void
    {
        $wasApproved = $request->status === LeaveRequestStatus::Approved;

        // Sama seperti approve(): pembatalan cuti yang sudah disetujui juga
        // mengembalikan hari-harinya ke absensi, jadi keduanya satu kesatuan.
        DB::transaction(function () use ($request, $wasApproved) {
            $request->update(['status' => LeaveRequestStatus::Cancelled]);

            if ($wasApproved) {
                $this->syncAttendance($request);
            }
        });

        app(ApprovalNotifier::class)->leaveCancelled($request, $wasApproved);
    }
}
