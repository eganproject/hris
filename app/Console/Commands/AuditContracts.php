<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Read-only audit of contract data. Its main purpose is to surface rows left behind
 * by the old edit-form bug: because the form keyed off currentContract (active only),
 * editing an employee whose contract had ended silently CREATED a second contract
 * instead of updating the existing one. That shows up as overlapping periods, or as
 * more than one active contract for the same employee.
 *
 * Reports only — deciding which row to keep needs a human, so nothing is changed.
 */
class AuditContracts extends Command
{
    protected $signature = 'contracts:audit {--employee= : Batasi ke satu ID karyawan}';

    protected $description = 'Periksa kontrak yang tumpang tindih atau ganda (mis. sisa dari bug form edit).';

    public function handle(): int
    {
        $employees = Employee::query()
            ->when($this->option('employee'), fn ($query, $id) => $query->whereKey($id))
            ->has('contracts')
            ->with(['contracts' => fn ($query) => $query->orderBy('start_date')->orderBy('id')])
            ->orderBy('full_name')
            ->get();

        $findings = [];

        foreach ($employees as $employee) {
            $issues = $this->issuesFor($employee->contracts);

            if ($issues !== []) {
                $findings[] = ['employee' => $employee, 'issues' => $issues];
            }
        }

        if ($findings === []) {
            $this->info('Tidak ada kontrak tumpang tindih atau ganda. Data kontrak bersih.');

            return self::SUCCESS;
        }

        $this->warn(count($findings).' karyawan punya kontrak yang perlu ditinjau:');
        $this->newLine();

        foreach ($findings as $finding) {
            /** @var Employee $employee */
            $employee = $finding['employee'];

            $this->line('<fg=yellow>'.$employee->full_name.'</> <fg=gray>('.($employee->employee_number ?: 'tanpa NIK').' · ID '.$employee->id.')</>');

            foreach ($finding['issues'] as $issue) {
                $this->line('  <fg=red>•</> '.$issue);
            }

            $this->table(
                ['Nomor', 'Jenis', 'Mulai', 'Selesai', 'Status'],
                $employee->contracts->map(fn (EmployeeContract $contract) => [
                    $contract->contract_number,
                    $contract->contract_type,
                    $contract->start_date?->format('d M Y') ?? '—',
                    $contract->end_date?->format('d M Y') ?? 'tanpa batas',
                    $contract->status_label,
                ])->all(),
            );
        }

        $this->newLine();
        $this->line('Perintah ini hanya melaporkan. Rapikan lewat menu Data Karyawan → detail karyawan,');
        $this->line('sisakan satu kontrak yang benar dan tutup/hapus baris kontrak yang keliru.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, EmployeeContract>  $contracts
     * @return list<string>
     */
    private function issuesFor(Collection $contracts): array
    {
        $issues = [];

        $active = $contracts->where('status', 'active');

        if ($active->count() > 1) {
            $issues[] = $active->count().' kontrak berstatus Aktif sekaligus (seharusnya paling banyak satu): '
                .$active->pluck('contract_number')->join(', ');
        }

        foreach ($contracts as $i => $contract) {
            foreach ($contracts->slice($i + 1) as $other) {
                if ($this->overlaps($contract, $other)) {
                    $issues[] = "Periode {$contract->contract_number} dan {$other->contract_number} tumpang tindih.";
                }
            }
        }

        return $issues;
    }

    /** A null end_date means open-ended (PKWTT), so it never stops overlapping. */
    private function overlaps(EmployeeContract $a, EmployeeContract $b): bool
    {
        if (! $a->start_date || ! $b->start_date) {
            return false;
        }

        $aStartsBeforeBEnds = $b->end_date === null || $a->start_date->lte($b->end_date);
        $bStartsBeforeAEnds = $a->end_date === null || $b->start_date->lte($a->end_date);

        return $aStartsBeforeBEnds && $bStartsBeforeAEnds;
    }
}
