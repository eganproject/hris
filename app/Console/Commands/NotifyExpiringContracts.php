<?php

namespace App\Console\Commands;

use App\Models\EmployeeContract;
use App\Support\ApprovalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Warn HR when an active contract is about to end. Fires on exact day thresholds
 * (H-30, H-14, H-7) so each contract triggers at most one reminder per threshold
 * instead of repeating every day.
 */
class NotifyExpiringContracts extends Command
{
    protected $signature = 'contracts:notify-expiring';

    protected $description = 'Kirim notifikasi ke HR untuk kontrak yang akan berakhir (H-30, H-14, H-7).';

    /** @var list<int> */
    private const THRESHOLDS = [30, 14, 7];

    public function handle(ApprovalNotifier $notifier): int
    {
        $today = Carbon::today();
        $sent = 0;

        foreach (self::THRESHOLDS as $days) {
            $targetDate = $today->copy()->addDays($days)->toDateString();

            EmployeeContract::query()
                ->where('status', 'active')
                ->whereDate('end_date', $targetDate)
                ->with('employee')
                ->get()
                ->each(function (EmployeeContract $contract) use ($notifier, $days, &$sent) {
                    $employee = $contract->employee;

                    if (! $employee || $employee->isInactive()) {
                        return;
                    }

                    $notifier->contractExpiring($employee, $contract, $days);
                    $sent++;
                });
        }

        $this->info("Notifikasi kontrak akan berakhir dikirim: {$sent}.");

        return self::SUCCESS;
    }
}
