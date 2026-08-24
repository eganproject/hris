<?php

namespace App\Support;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Peta tanggal => cuti/izin milik satu karyawan dalam satu rentang, dipisah menjadi
 * dua lapis yang artinya berbeda:
 *
 *  - DISETUJUI: menentukan bagaimana hari itu dijalani, jadi inilah yang diberikan ke
 *    WorkMode. Harus sejalan dengan AttendanceResolver — WFH & dinas luar tetap hari
 *    kerja, cuti/izin/sakit tidak.
 *  - MENUNGGU: belum mengubah apa pun pada jadwal. Hanya penanda bahwa pengajuannya
 *    sudah masuk, supaya karyawan yang baru mengajukan tidak melihat harinya seolah
 *    tidak terjadi apa-apa lalu mengira pengajuannya gagal terkirim.
 *
 * Dipakai bersama oleh "Jadwal Saya" dan "Tukar Jadwal Saya". Sebelumnya keduanya
 * menghitung ini sendiri-sendiri dengan aturan berbeda, sehingga hari yang sama bisa
 * tampil berlainan tergantung halaman mana yang dibuka.
 */
final class LeaveCalendar
{
    /**
     * @param  array<string, LeaveRequest>  $approved
     * @param  array<string, LeaveRequest>  $pending
     */
    private function __construct(
        private readonly array $approved,
        private readonly array $pending,
    ) {}

    public static function for(Employee $employee, CarbonInterface $from, CarbonInterface $to): self
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        $leaves = $employee->leaveRequests()
            ->whereIn('status', [
                LeaveRequestStatus::Approved->value,
                LeaveRequestStatus::PendingSupervisor->value,
                LeaveRequestStatus::PendingHr->value,
            ])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with('leaveType')
            ->get();

        $approved = [];
        $pending = [];

        foreach ($leaves as $leave) {
            // Dipotong ke rentang yang diminta: pengajuan panjang tidak perlu
            // menghasilkan tanggal di luar layar yang sedang dilihat.
            $leaveStart = $leave->start_date->greaterThan($start) ? $leave->start_date : $start;
            $leaveEnd = $leave->end_date->lessThan($end) ? $leave->end_date : $end;

            foreach (CarbonPeriod::create($leaveStart, $leaveEnd) as $day) {
                $key = $day->toDateString();

                if ($leave->status === LeaveRequestStatus::Approved) {
                    $approved[$key] = $leave;
                } elseif (! isset($pending[$key])) {
                    $pending[$key] = $leave;
                }
            }
        }

        return new self($approved, $pending);
    }

    /** Cuti yang sudah disetujui pada tanggal itu — yang diberikan ke WorkMode. */
    public function approvedOn(string $date): ?LeaveRequest
    {
        return $this->approved[$date] ?? null;
    }

    /**
     * Pengajuan yang masih menunggu keputusan pada tanggal itu. Hari yang sudah punya
     * cuti disetujui tidak pernah ikut: keputusannya sudah ada, jadi menandainya
     * "diajukan" hanya akan membingungkan.
     */
    public function pendingOn(string $date): ?LeaveRequest
    {
        return isset($this->approved[$date]) ? null : ($this->pending[$date] ?? null);
    }
}
