<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->mergeDepartment('HR', [
                'code' => 'AKR',
                'name' => 'Akrilik & Aksesoris',
                'description' => 'Produksi, perakitan, dan pengelolaan aksesoris.',
            ]);
            $this->mergeDepartment('OPS', [
                'code' => 'OTO',
                'name' => 'Otomotif',
                'description' => 'Operasional dan layanan terkait otomotif.',
            ]);
            $this->mergeDepartment('FIN', [
                'code' => 'ACC',
                'name' => 'Accounting',
                'description' => 'Accounting, payroll, dan administrasi keuangan.',
            ]);

            $this->renamePosition('HR-SPV', 'AKR-SPV', 'Supervisor');
            $this->renamePosition('OPS-STF', 'OTO-STF', 'Staff');
            $this->renamePosition('WHS-ADM', 'GUD-ADM', 'Admin');
            $this->renamePosition('FIN-STF', 'ACC-STF', 'Staff');

            $this->syncBranchDepartments();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->mergeDepartment('AKR', [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'People operations and employee administration.',
            ]);
            $this->mergeDepartment('OTO', [
                'code' => 'OPS',
                'name' => 'Operations',
                'description' => 'Daily operational execution across locations.',
            ]);
            $this->mergeDepartment('ACC', [
                'code' => 'FIN',
                'name' => 'Finance',
                'description' => 'Finance, payroll, and accounting.',
            ]);

            $this->renamePosition('AKR-SPV', 'HR-SPV', 'HR Supervisor');
            $this->renamePosition('OTO-STF', 'OPS-STF', 'Operations Staff');
            $this->renamePosition('GUD-ADM', 'WHS-ADM', 'Warehouse Admin');
            $this->renamePosition('ACC-STF', 'FIN-STF', 'Finance Staff');
        });
    }

    /**
     * @param array{code: string, name: string, description: string} $target
     */
    private function mergeDepartment(string $oldCode, array $target): void
    {
        $oldDepartment = DB::table('departments')->where('code', $oldCode)->first();
        $targetDepartment = DB::table('departments')->where('code', $target['code'])->first();

        if (! $oldDepartment && ! $targetDepartment) {
            return;
        }

        if ($oldDepartment && ! $targetDepartment) {
            DB::table('departments')
                ->where('id', $oldDepartment->id)
                ->update([...$target, 'is_active' => true, 'updated_at' => now()]);

            return;
        }

        DB::table('departments')
            ->where('id', $targetDepartment->id)
            ->update(['name' => $target['name'], 'description' => $target['description'], 'is_active' => true, 'updated_at' => now()]);

        if (! $oldDepartment || $oldDepartment->id === $targetDepartment->id) {
            return;
        }

        DB::table('branch_department')
            ->where('department_id', $oldDepartment->id)
            ->orderBy('branch_id')
            ->each(function (object $placement) use ($targetDepartment): void {
                DB::table('branch_department')->updateOrInsert(
                    ['branch_id' => $placement->branch_id, 'department_id' => $targetDepartment->id],
                    [
                        'is_primary' => $placement->is_primary,
                        'is_active' => $placement->is_active,
                        'created_at' => $placement->created_at ?? now(),
                        'updated_at' => now(),
                    ],
                );
            });

        DB::table('branch_department')->where('department_id', $oldDepartment->id)->delete();
        DB::table('employees')->where('department_id', $oldDepartment->id)->update(['department_id' => $targetDepartment->id]);
        DB::table('job_positions')->where('department_id', $oldDepartment->id)->update(['department_id' => $targetDepartment->id]);
        DB::table('departments')->where('id', $oldDepartment->id)->delete();
    }

    private function renamePosition(string $oldCode, string $newCode, string $name): void
    {
        $position = DB::table('job_positions')->where('code', $oldCode)->first()
            ?: DB::table('job_positions')->where('code', $newCode)->first();

        if (! $position) {
            return;
        }

        DB::table('job_positions')
            ->where('id', $position->id)
            ->update(['code' => $newCode, 'name' => $name, 'updated_at' => now()]);
    }

    private function syncBranchDepartments(): void
    {
        $departments = DB::table('departments')->whereIn('code', ['AKR', 'OTO', 'ACC'])->pluck('id', 'code');
        $branches = DB::table('branches')->whereIn('code', ['SBY-OFC-01', 'SBY-WHS-01', 'JKT-OFC-01'])->pluck('id', 'code');

        $matrix = [
            'SBY-OFC-01' => ['AKR' => true, 'OTO' => false, 'ACC' => false],
            'SBY-WHS-01' => ['AKR' => true],
            'JKT-OFC-01' => ['AKR' => false, 'OTO' => true, 'ACC' => false],
        ];

        foreach ($matrix as $branchCode => $departmentCodes) {
            if (! isset($branches[$branchCode])) {
                continue;
            }

            foreach ($departmentCodes as $departmentCode => $isPrimary) {
                if (! isset($departments[$departmentCode])) {
                    continue;
                }

                DB::table('branch_department')->updateOrInsert(
                    ['branch_id' => $branches[$branchCode], 'department_id' => $departments[$departmentCode]],
                    ['is_primary' => $isPrimary, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }
};
