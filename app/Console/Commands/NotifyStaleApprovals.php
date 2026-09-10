<?php

namespace App\Console\Commands;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Support\ApprovalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Tagih pengajuan cuti & lembur yang belum juga diputuskan atasannya.
 *
 * Menyala pada ambang hari yang persis (H+3 lalu H+7 sejak diajukan), sama seperti
 * NotifyExpiringContracts, sehingga satu pengajuan paling banyak ditagih sekali per
 * ambang. Cara ini sengaja dipilih agar tidak perlu kolom "terakhir diingatkan" —
 * kolom seperti itu hanya berguna kalau pengingatnya berjadwal bebas, dan di sini
 * tidak.
 *
 * Pada ambang kedua, atasan di atasnya ikut ditembusi: pengajuan yang sudah seminggu
 * didiamkan bukan lagi soal lupa, dan karyawannya tidak punya jalan lain untuk
 * mendorongnya.
 */
class NotifyStaleApprovals extends Command
{
    protected $signature = 'approvals:notify-stale';

    protected $description = 'Ingatkan atasan atas pengajuan cuti & lembur yang belum diputuskan (H+3, lalu H+7 dengan tembusan ke atasan di atasnya).';

    /**
     * Ambang pertama sekadar mengingatkan; ambang kedua menaikkannya.
     *
     * @var array<int, bool> hari menunggu => naikkan ke atasan di atasnya
     */
    private const THRESHOLDS = [3 => false, 7 => true];

    public function handle(ApprovalNotifier $notifier): int
    {
        $today = Carbon::today();
        $sent = 0;

        foreach (self::THRESHOLDS as $days => $escalate) {
            $submittedOn = $today->copy()->subDays($days)->toDateString();

            // Tanpa atasan, pengajuannya tidak pernah berstatus menunggu atasan — tapi
            // whereNotNull tetap dipasang supaya tidak ada notifikasi yang menguap ke
            // penerima kosong bila data lama sempat tidak konsisten.
            LeaveRequest::query()
                ->where('status', LeaveRequestStatus::PendingSupervisor->value)
                ->whereNotNull('supervisor_id')
                ->whereDate('created_at', $submittedOn)
                ->with(['employee', 'supervisor', 'leaveType'])
                ->each(function (LeaveRequest $leave) use ($notifier, $days, $escalate, &$sent): void {
                    $notifier->leavePendingReminder($leave, $days, $escalate);
                    $sent++;
                });

            OvertimeApproval::query()
                ->where('status', OvertimeApproval::STATUS_PENDING)
                ->whereNotNull('supervisor_id')
                ->whereDate('requested_at', $submittedOn)
                ->with(['employee', 'supervisor'])
                ->each(function (OvertimeApproval $overtime) use ($notifier, $days, $escalate, &$sent): void {
                    $notifier->overtimePendingReminder($overtime, $days, $escalate);
                    $sent++;
                });
        }

        $this->info("Pengingat pengajuan mengendap dikirim: {$sent}.");

        return self::SUCCESS;
    }
}
