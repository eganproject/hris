<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\ScheduleGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rolling roster generation. Keeps every active employee's daily schedule
 * materialized from today up to a forward horizon, so HR never has to press
 * "Generate Roster" each month. Employees on a fixed weekly pattern (e.g. office
 * hours) are simply assigned once and stay scheduled forever; manual overrides are
 * preserved by the generator.
 */
class GenerateRoster extends Command
{
    protected $signature = 'schedule:generate-roster {--days=60 : Jumlah hari ke depan yang dijaga selalu terisi}';

    protected $description = 'Perpanjang roster (jadwal harian) ke depan dari pola/assignment aktif — pengganti generate manual.';

    public function handle(ScheduleGenerator $generator): int
    {
        $from = Carbon::today();
        $to = $from->copy()->addDays(max(1, (int) $this->option('days')));
        $written = 0;

        Employee::query()
            ->active()
            ->chunkById(200, function ($employees) use ($generator, $from, $to, &$written) {
                foreach ($employees as $employee) {
                    $written += $generator->forEmployee($employee, $from, $to);
                }
            });

        $this->info("Roster diperpanjang s/d {$to->toDateString()} — {$written} hari ditulis/diperbarui.");

        return self::SUCCESS;
    }
}
