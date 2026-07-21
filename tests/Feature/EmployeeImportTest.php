<?php

use App\Imports\EmployeesImport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * One import row, keyed the way WithHeadingRow hands them to the importer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importRow(array $overrides = []): array
{
    return [
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_bergabung' => '2026-07-05',
        'status_kepegawaian' => 'Aktif',
        'lokasi_kerja' => 'Kantor Pusat',
        'divisi' => 'Operasional',
        'jabatan' => 'Staf',
        'nomor_kontrak' => 'KTR-001',
        'jenis_kontrak' => 'PKWT',
        'tanggal_mulai_kontrak' => '2026-07-05',
        'tanggal_selesai_kontrak' => '2027-07-04',
        ...$overrides,
    ];
}

function runImport(array $rows): EmployeesImport
{
    $import = new EmployeesImport;
    $import->collection(collect($rows)->map(fn (array $row) => collect($row)));

    return $import;
}

test('imported employees get a generated code and the file never sets it', function () {
    $import = runImport([
        importRow(['nomor_karyawan' => 'DIABAIKAN-001']),
    ]);

    expect($import->errors())->toBe([])
        ->and($import->imported())->toBe(1);

    $employee = Employee::query()->firstOrFail();

    // "Kantor Pusat" is a new location, so the importer coins the code KP for it.
    expect($employee->employee_number)->toBe(sprintf('COK0726-KP%04d', $employee->id));
});

test('a manager can be referenced by the full name of somebody created in the same file', function () {
    $import = runImport([
        importRow(['nama_lengkap' => 'Dewi Anggraeni', 'nomor_kontrak' => 'KTR-001']),
        importRow(['nama_lengkap' => 'Budi Santoso', 'nomor_kontrak' => 'KTR-002', 'nomor_nama_atasan' => 'dewi anggraeni']),
    ]);

    expect($import->errors())->toBe([]);

    $manager = Employee::query()->where('full_name', 'Dewi Anggraeni')->firstOrFail();
    $employee = Employee::query()->where('full_name', 'Budi Santoso')->firstOrFail();

    expect($employee->manager_id)->toBe($manager->id);
});

test('a manager can be referenced by the generated code of an existing employee', function () {
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Kantor Pusat', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $position = JobPosition::query()->create(['code' => 'SPV', 'name' => 'Supervisor', 'is_active' => true]);

    $manager = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Atasan Lama',
        'join_date' => '2026-01-10',
        'employment_status' => 'active',
    ]);

    $import = runImport([
        importRow(['nomor_nama_atasan' => $manager->employee_number]),
    ]);

    expect($import->errors())->toBe([]);

    $employee = Employee::query()->where('full_name', 'Budi Santoso')->firstOrFail();

    expect($employee->manager_id)->toBe($manager->id)
        // The location already exists, so its own code (HO) is used.
        ->and($employee->employee_number)->toBe(sprintf('COK0726-HO%04d', $employee->id));
});

test('the ikut jam kantor column marks the employee to follow office hours', function () {
    $import = runImport([
        importRow(['nama_lengkap' => 'Office Worker', 'nomor_kontrak' => 'KTR-OH1', 'ikut_jam_kantor' => 'Ya']),
        importRow(['nama_lengkap' => 'Shift Worker', 'nomor_kontrak' => 'KTR-OH2', 'ikut_jam_kantor' => 'Tidak']),
        importRow(['nama_lengkap' => 'Default Worker', 'nomor_kontrak' => 'KTR-OH3']),
    ]);

    expect($import->errors())->toBe([]);

    expect(Employee::query()->where('full_name', 'Office Worker')->value('follows_office_hours'))->toBeTrue()
        ->and(Employee::query()->where('full_name', 'Shift Worker')->value('follows_office_hours'))->toBeFalse()
        // Kolom dikosongkan = default Tidak.
        ->and(Employee::query()->where('full_name', 'Default Worker')->value('follows_office_hours'))->toBeFalse();
});

test('an unrecognised ikut jam kantor value is reported and nothing is imported', function () {
    $import = runImport([
        importRow(['ikut_jam_kantor' => 'mungkin']),
    ]);

    expect($import->imported())->toBe(0)
        ->and(Employee::query()->count())->toBe(0)
        ->and($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('Ikut Jam Kantor');
});

test('an unknown manager reference is reported and nothing is imported', function () {
    $import = runImport([
        importRow(['nomor_nama_atasan' => 'Siapa Ini']),
    ]);

    expect($import->imported())->toBe(0)
        ->and(Employee::query()->count())->toBe(0)
        ->and($import->errors())->toHaveCount(1)
        ->and($import->errors()[0])->toContain('Atasan "Siapa Ini" tidak ditemukan');
});
