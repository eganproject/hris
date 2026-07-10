<?php

namespace App\Console\Commands;

use App\Actions\ProcessDayAttendance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly attendance close-out. Employees who punch in/out are already resolved in
 * real time by the device feed; this fills the rest for a completed day — marking
 * Absent (scheduled, no punch), Leave, Holiday, and DayOff — so reports and payroll
 * are complete without anyone pressing "Proses" manually.
 *
 * Runs the exact same logic as the manual button (ProcessDayAttendance) and is
 * idempotent, so re-runs and overlaps with the live feed never corrupt data.
 */
class ProcessDailyAttendance extends Command
{
    protected $signature = 'attendance:process-day
        {--date= : Tanggal acuan (Y-m-d). Default: kemarin}
        {--days=1 : Berapa hari ke belakang (termasuk tanggal acuan) yang diproses}';

    protected $description = 'Proses/tutup absensi harian: tandai Alfa/Cuti/Libur untuk karyawan tanpa punch.';

    public function handle(ProcessDayAttendance $processDay): int
    {
        $end = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::yesterday();

        $days = max(1, (int) $this->option('days'));

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $end->copy()->subDays($offset);
            $count = $processDay->handle($date);

            $this->info("Absensi {$date->toDateString()} diproses: {$count} karyawan.");
        }

        return self::SUCCESS;
    }
}
