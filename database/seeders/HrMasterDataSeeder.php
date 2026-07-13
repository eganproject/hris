<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class HrMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $employeeRole = Role::findOrCreate('employee', 'web');
        $employeeReaderRole = Role::findOrCreate('employee-reader', 'web');

        $branches = collect([
            ['code' => 'SBY-OFC-01', 'name' => 'Surabaya Office 1', 'type' => 'office', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'address' => 'Jl. Raya Darmo, Surabaya'],
            ['code' => 'SBY-WHS-01', 'name' => 'Surabaya Gudang 1', 'type' => 'warehouse', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'address' => 'Pergudangan Margomulyo, Surabaya'],
            ['code' => 'JKT-OFC-01', 'name' => 'Jakarta Office 1', 'type' => 'office', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'address' => 'Jl. Sudirman, Jakarta'],
        ])->mapWithKeys(fn (array $branch) => [
            $branch['code'] => Branch::query()->updateOrCreate(['code' => $branch['code']], [...$branch, 'is_active' => true]),
        ]);

        collect([
            ['code' => 'REG', 'name' => 'Regular', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60],
            ['code' => 'PAGI', 'name' => 'Shift Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'break_minutes' => 45],
            ['code' => 'SORE', 'name' => 'Shift Sore', 'start_time' => '15:00', 'end_time' => '23:00', 'break_minutes' => 45],
        ])->each(fn (array $shift) => Shift::query()->updateOrCreate(['code' => $shift['code']], [...$shift, 'is_active' => true]));

        $departments = collect([
            ['code' => 'AKR', 'name' => 'Akrilik & Aksesoris', 'description' => 'Produksi, perakitan, dan pengelolaan aksesoris.'],
            ['code' => 'OTO', 'name' => 'Otomotif', 'description' => 'Operasional dan layanan terkait otomotif.'],
            ['code' => 'ACC', 'name' => 'Accounting', 'description' => 'Accounting dan administrasi keuangan.'],
        ])->mapWithKeys(fn (array $department) => [
            $department['code'] => Department::query()->updateOrCreate(['code' => $department['code']], [...$department, 'is_active' => true]),
        ]);

        $positions = collect([
            ['code' => 'SPV', 'name' => 'Supervisor', 'level' => 'Supervisor', 'departments' => ['AKR', 'OTO', 'ACC']],
            ['code' => 'STF', 'name' => 'Staff', 'level' => 'Staff', 'departments' => ['AKR', 'OTO', 'ACC']],
            ['code' => 'ADM', 'name' => 'Admin', 'level' => 'Staff', 'departments' => ['AKR', 'OTO', 'ACC']],
        ])->mapWithKeys(function (array $position) use ($departments, $employeeReaderRole, $employeeRole): array {
            $jobPosition = JobPosition::query()->updateOrCreate(
                ['code' => $position['code']],
                [
                    'default_role_id' => $position['code'] === 'SPV' ? $employeeReaderRole->id : $employeeRole->id,
                    'name' => $position['name'],
                    'level' => $position['level'],
                    'is_active' => true,
                ],
            );

            $jobPosition->departments()->sync(
                collect($position['departments'])
                    ->mapWithKeys(fn (string $departmentCode): array => [
                        $departments[$departmentCode]->id => ['is_active' => true],
                    ])
                    ->all(),
            );

            return [$position['code'] => $jobPosition];
        });

        $branches['SBY-OFC-01']->departments()->sync([
            $departments['AKR']->id => ['is_primary' => true, 'is_active' => true],
            $departments['OTO']->id => ['is_primary' => false, 'is_active' => true],
            $departments['ACC']->id => ['is_primary' => false, 'is_active' => true],
        ]);
        $branches['SBY-WHS-01']->departments()->sync([
            $departments['AKR']->id => ['is_primary' => true, 'is_active' => true],
        ]);
        $branches['JKT-OFC-01']->departments()->sync([
            $departments['AKR']->id => ['is_primary' => false, 'is_active' => true],
            $departments['OTO']->id => ['is_primary' => true, 'is_active' => true],
            $departments['ACC']->id => ['is_primary' => false, 'is_active' => true],
        ]);

        collect([
            [
                'full_name' => 'Dewi Anggraeni',
                'email' => 'dewi.anggraeni@example.test',
                'phone' => '081200000001',
                'branch' => 'SBY-OFC-01',
                'department' => 'AKR',
                'position' => 'SPV',
                'join_date' => now()->subYears(2)->toDateString(),
                'contract_number' => 'CTR-2024-0001',
                'contract_type' => 'PKWTT',
                'contract_start_date' => now()->subYears(2)->toDateString(),
                'contract_end_date' => null,
                'login_email' => 'dewi.anggraeni@login.test',
            ],
            [
                'full_name' => 'Bima Prasetyo',
                'email' => 'bima.prasetyo@example.test',
                'phone' => '081200000002',
                'branch' => 'SBY-WHS-01',
                'department' => 'AKR',
                'position' => 'ADM',
                'join_date' => now()->subMonths(10)->toDateString(),
                'contract_number' => 'CTR-2025-0002',
                'contract_type' => 'PKWT',
                'contract_start_date' => now()->subMonths(10)->toDateString(),
                'contract_end_date' => now()->addDays(21)->toDateString(),
                'login_email' => null,
            ],
            [
                'full_name' => 'Citra Lestari',
                'email' => 'citra.lestari@example.test',
                'phone' => '081200000003',
                'branch' => 'JKT-OFC-01',
                'department' => 'ACC',
                'position' => 'STF',
                'join_date' => now()->subMonths(4)->toDateString(),
                'contract_number' => 'CTR-2025-0003',
                'contract_type' => 'Probation',
                'contract_start_date' => now()->subMonths(4)->toDateString(),
                'contract_end_date' => now()->addMonths(2)->toDateString(),
                'login_email' => null,
            ],
        ])->each(function (array $data) use ($branches, $departments, $positions): void {
            $user = null;

            if ($data['login_email']) {
                $user = User::query()->updateOrCreate(
                    ['email' => $data['login_email']],
                    [
                        'name' => $data['full_name'],
                        'password' => 'Password!2',
                    ],
                );

                $defaultRole = Role::query()->find($positions[$data['position']]->default_role_id);

                $user->syncRoles([$defaultRole?->name ?? 'employee']);
            }

            // Keyed by email: the employee code is generated by the system, so it is
            // not something the seeder can key on.
            $employee = Employee::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user?->id,
                    'branch_id' => $branches[$data['branch']]->id,
                    'department_id' => $departments[$data['department']]->id,
                    'job_position_id' => $positions[$data['position']]->id,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'join_date' => $data['join_date'],
                    'employment_status' => 'active',
                ],
            );

            $employee->contracts()->updateOrCreate(
                ['contract_number' => $data['contract_number']],
                [
                    'contract_type' => $data['contract_type'],
                    'start_date' => $data['contract_start_date'],
                    'end_date' => $data['contract_end_date'],
                    'status' => 'active',
                    'notes' => 'Seeded sample contract.',
                ],
            );
        });
    }
}
