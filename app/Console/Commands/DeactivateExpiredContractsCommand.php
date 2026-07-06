<?php

namespace App\Console\Commands;

use App\Actions\DeactivateExpiredContracts;
use Illuminate\Console\Command;

class DeactivateExpiredContractsCommand extends Command
{
    protected $signature = 'employees:deactivate-expired';

    protected $description = 'Menonaktifkan karyawan yang masa kontraknya sudah berakhir.';

    public function handle(DeactivateExpiredContracts $action): int
    {
        $count = $action->run();

        $this->info("Selesai. {$count} karyawan dinonaktifkan karena masa kontrak berakhir.");

        return self::SUCCESS;
    }
}
