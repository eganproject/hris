<?php

use App\Support\EmployeeNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites every existing employee code into the generated format
 * COK[bulan][tahun bergabung]-[kode lokasi][id], e.g. COK0726-SBYOFC010012.
 * From here on the code is derived data, maintained by the Employee model.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
            ->orderBy('employees.id')
            ->select(['employees.id', 'employees.join_date', 'branches.code as branch_code'])
            ->chunk(200, function ($employees): void {
                foreach ($employees as $employee) {
                    $joinDate = $employee->join_date ? Carbon::parse($employee->join_date) : null;

                    DB::table('employees')->where('id', $employee->id)->update([
                        'employee_number' => EmployeeNumber::format($joinDate, $employee->branch_code, (int) $employee->id),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The previous, hand-typed codes are not recoverable.
    }
};
