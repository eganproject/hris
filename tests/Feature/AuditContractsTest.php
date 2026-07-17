<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contractFor(Employee $employee, string $number, string $start, ?string $end, string $status): void
{
    $employee->contracts()->create([
        'contract_number' => $number,
        'contract_type' => $end === null ? 'PKWTT' : 'PKWT',
        'start_date' => $start,
        'end_date' => $end,
        'status' => $status,
    ]);
}

test('a clean contract history reports nothing', function () {
    $employee = Employee::query()->create(['full_name' => 'Rapi', 'employment_status' => 'active']);

    // Back-to-back contracts that do not overlap: renewed, then the current one.
    contractFor($employee, 'CTR-1', '2024-01-01', '2024-12-31', 'renewed');
    contractFor($employee, 'CTR-2', '2025-01-01', '2025-12-31', 'active');

    $this->artisan('contracts:audit')
        ->expectsOutputToContain('Data kontrak bersih')
        ->assertExitCode(0);
});

test('it flags contracts left overlapping by the old edit-form bug', function () {
    $employee = Employee::query()->create(['full_name' => 'Tumpang Tindih', 'employment_status' => 'active']);

    // The expired contract still covers 2025 while a second one was created over it.
    contractFor($employee, 'CTR-LAMA', '2025-01-01', '2025-12-31', 'expired');
    contractFor($employee, 'CTR-BARU', '2025-06-01', '2026-05-31', 'active');

    $this->artisan('contracts:audit')
        ->expectsOutputToContain('Tumpang Tindih')
        ->expectsOutputToContain('tumpang tindih')
        ->assertExitCode(0);
});

test('it flags an employee holding more than one active contract', function () {
    $employee = Employee::query()->create(['full_name' => 'Dobel Aktif', 'employment_status' => 'active']);

    contractFor($employee, 'CTR-A', '2024-01-01', '2024-06-30', 'active');
    contractFor($employee, 'CTR-B', '2025-01-01', '2025-06-30', 'active');

    $this->artisan('contracts:audit')
        ->expectsOutputToContain('2 kontrak berstatus Aktif sekaligus')
        ->assertExitCode(0);
});

test('an open-ended PKWTT overlaps any later contract', function () {
    $employee = Employee::query()->create(['full_name' => 'Tetap', 'employment_status' => 'active']);

    contractFor($employee, 'CTR-TETAP', '2024-01-01', null, 'active');
    contractFor($employee, 'CTR-SETELAH', '2025-01-01', '2025-12-31', 'renewed');

    $this->artisan('contracts:audit')
        ->expectsOutputToContain('tumpang tindih')
        ->assertExitCode(0);
});

test('the audit can be limited to a single employee', function () {
    $bersih = Employee::query()->create(['full_name' => 'Bersih', 'employment_status' => 'active']);
    contractFor($bersih, 'CTR-OK', '2025-01-01', '2025-12-31', 'active');

    $bermasalah = Employee::query()->create(['full_name' => 'Bermasalah', 'employment_status' => 'active']);
    contractFor($bermasalah, 'CTR-X', '2025-01-01', '2025-12-31', 'expired');
    contractFor($bermasalah, 'CTR-Y', '2025-06-01', '2026-05-31', 'active');

    // Scoped to the clean employee → nothing to report.
    $this->artisan('contracts:audit', ['--employee' => $bersih->id])
        ->expectsOutputToContain('Data kontrak bersih')
        ->assertExitCode(0);

    $this->artisan('contracts:audit', ['--employee' => $bermasalah->id])
        ->expectsOutputToContain('Bermasalah')
        ->assertExitCode(0);
});
