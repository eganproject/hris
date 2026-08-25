<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Penyaring "Tumpang Tindih" mencari duplikat lewat periode yang beririsan, bukan
 * lewat "punya lebih dari satu kontrak" — karyawan lama yang kontraknya berkali-kali
 * diperpanjang memang punya banyak kontrak, dan itu wajar.
 */
function overlapEmployee(string $name = 'Budi'): Employee
{
    $branch = Branch::query()->firstOrCreate(['code' => 'HO'], ['name' => 'Pusat', 'is_active' => true]);

    return Employee::query()->create([
        'full_name' => $name, 'employment_status' => 'active',
        'join_date' => '2025-01-01', 'branch_id' => $branch->id,
    ]);
}

function overlapContract(Employee $employee, string $number, ?string $start, ?string $end, string $status = 'active'): EmployeeContract
{
    return $employee->contracts()->create([
        'contract_number' => $number,
        'contract_type' => $end === null ? 'PKWTT' : 'PKWT',
        'start_date' => $start, 'end_date' => $end, 'status' => $status,
    ]);
}

function overlappingNumbers(): array
{
    return EmployeeContract::query()->overlapping()->orderBy('id')->pluck('contract_number')->all();
}

test('sequential renewals are never flagged, however many there are', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-2024', '2024-01-01', '2024-12-31', 'renewed');
    overlapContract($e, 'KTR-2025', '2025-01-01', '2025-12-31', 'renewed');
    overlapContract($e, 'KTR-2026', '2026-01-01', '2026-12-31');

    expect(overlappingNumbers())->toBe([]);
});

test('two contracts covering the same period flag each other', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-ASLI', '2026-01-01', '2026-12-31');
    overlapContract($e, 'KTR-DUPLIKAT', '2026-01-01', '2026-12-31');

    // Keduanya muncul: HR perlu melihat kedua sisi untuk memutuskan mana yang dibuang.
    expect(overlappingNumbers())->toBe(['KTR-ASLI', 'KTR-DUPLIKAT']);
});

test('a short junk row inside a long real contract is caught', function () {
    // Bentuk yang benar-benar ada di data: kontrak asli panjang, lalu baris sampah
    // berdurasi satu hari di tengahnya.
    $e = overlapEmployee();
    overlapContract($e, 'CTR-2025-0002', '2025-09-06', '2026-07-27', 'ended_early');
    overlapContract($e, 'PCALOIWI', '2026-07-06', '2026-07-07', 'completed');

    expect(overlappingNumbers())->toBe(['CTR-2025-0002', 'PCALOIWI']);
});

test('an open-ended contract overlaps everything that starts after it', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-TETAP', '2025-01-01', null);       // PKWTT, tanpa batas
    overlapContract($e, 'KTR-NYASAR', '2026-05-01', '2026-06-01');

    expect(overlappingNumbers())->toBe(['KTR-TETAP', 'KTR-NYASAR']);
});

test('periods that merely touch at the boundary are not treated as overlapping', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-A', '2025-01-01', '2025-12-31', 'renewed');
    overlapContract($e, 'KTR-B', '2026-01-01', '2026-12-31');

    expect(overlappingNumbers())->toBe([]);
});

test('contracts of different employees never flag each other', function () {
    $a = overlapEmployee('Budi');
    $b = overlapEmployee('Citra');
    overlapContract($a, 'KTR-A', '2026-01-01', '2026-12-31');
    overlapContract($b, 'KTR-B', '2026-01-01', '2026-12-31');

    expect(overlappingNumbers())->toBe([]);
});

test('a contract with no start date is skipped rather than guessed at', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-OK', '2026-01-01', '2026-12-31');
    overlapContract($e, 'KTR-TANPA-TANGGAL', null, null);

    expect(overlappingNumbers())->toBe([]);
});

test('the filter and its summary card are reachable from the page', function () {
    $e = overlapEmployee();
    overlapContract($e, 'KTR-ASLI', '2026-01-01', '2026-12-31');
    overlapContract($e, 'KTR-DUPLIKAT', '2026-01-01', '2026-12-31');
    overlapContract(overlapEmployee('Citra'), 'KTR-BERSIH', '2026-01-01', '2026-12-31');

    $this->actingAs(contractViewer())
        ->get(route('employees.contracts.index', ['filter' => 'overlapping']))
        ->assertOk()
        ->assertSee('Tumpang Tindih')
        ->assertSee('KTR-ASLI')
        ->assertSee('KTR-DUPLIKAT')
        ->assertDontSee('KTR-BERSIH');
});
