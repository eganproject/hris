<?php

namespace App\Actions;

use App\Models\Employee;
use App\Support\ApprovalNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeactivateExpiredContracts
{
    /**
     * Deactivate every still-active employee whose current contract period has
     * already ended. Their status becomes "Sudah Keluar" with exit reason
     * "Kontrak Berakhir" and the exit date set to the contract's end date.
     *
     * @return int Number of employees deactivated.
     */
    public function run(): int
    {
        $count = 0;

        Employee::query()
            ->where('employment_status', '!=', 'inactive')
            ->whereHas('contracts', function ($query) {
                $query->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->whereDate('end_date', '<', Carbon::today());
            })
            ->with(['currentContract', 'user'])
            ->chunkById(100, function ($employees) use (&$count) {
                foreach ($employees as $employee) {
                    $contract = $employee->currentContract;

                    if (! $contract || ! $contract->end_date || $contract->end_date->gte(Carbon::today())) {
                        continue;
                    }

                    DB::transaction(function () use ($employee, $contract) {
                        $employee->markAsExited(
                            'contract_ended',
                            $contract->end_date,
                            $employee->exit_notes ?: 'Dinonaktifkan otomatis karena masa kontrak telah berakhir.',
                        );

                        $employee->recordEvent(
                            'contract_ended',
                            "Dinonaktifkan otomatis: masa kontrak {$contract->contract_number} berakhir pada ".$contract->end_date->format('d M Y').'.',
                            $contract->end_date,
                            ['contract_number' => $contract->contract_number],
                        );
                    });

                    app(ApprovalNotifier::class)->contractAutoDeactivated($employee, $contract);

                    // Proses keluar manual DIBLOKIR bila asetnya belum kembali, tapi
                    // yang otomatis ini tidak boleh ikut berhenti: menahannya hanya
                    // membuat kontrak kedaluwarsa menumpuk diam-diam, dan orang yang
                    // sudah tidak bekerja tetap terhitung aktif. Jadi ia tetap
                    // dinonaktifkan, dan yang mengurus aset diberi tahu agar barangnya
                    // bisa dikejar.
                    $assets = $employee->openAssetCount();

                    if ($assets > 0) {
                        app(ApprovalNotifier::class)->employeeExitedWithAssets($employee, $assets);
                    }

                    $count++;
                }
            });

        return $count;
    }
}
