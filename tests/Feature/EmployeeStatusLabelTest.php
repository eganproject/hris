<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the employment status label and tone follow the status', function () {
    $employee = Employee::query()->create(['full_name' => 'Aktif', 'employment_status' => 'active']);

    expect($employee->employment_status_label)->toBe('Aktif')
        ->and($employee->employment_status_tone)->toBe('success');

    $employee->forceFill(['employment_status' => 'inactive'])->save();

    expect($employee->fresh()->employment_status_label)->toBe('Nonaktif')
        ->and($employee->fresh()->employment_status_tone)->toBe('danger');
});

test('an unrecognised status degrades to a readable label instead of blowing up', function () {
    $employee = Employee::query()->create(['full_name' => 'Aneh', 'employment_status' => 'on_leave']);

    expect($employee->employment_status_label)->toBe('On Leave')
        ->and($employee->employment_status_tone)->toBe('neutral');
});

test('there is exactly one accessor for the employment status', function () {
    // Dulu ada sepasang accessor bernama kepegawaian_* yang menghasilkan hal yang
    // sama persis. Tes ini menjaga agar duplikatnya tidak diam-diam muncul lagi.
    $methods = get_class_methods(Employee::class);

    expect($methods)->not->toContain('getKepegawaianStatusLabelAttribute')
        ->and($methods)->not->toContain('getKepegawaianStatusToneAttribute')
        ->and($methods)->toContain('getEmploymentStatusLabelAttribute')
        ->and($methods)->toContain('getEmploymentStatusToneAttribute');
});

test('hr_status_label answers a different question: status plus contract condition', function () {
    $employee = Employee::query()->create(['full_name' => 'Tanpa Kontrak', 'employment_status' => 'active']);

    // Tetap memuat status kepegawaiannya, lalu menambahkan kondisi kontrak — jadi
    // memang bukan duplikat dari employment_status_label.
    expect($employee->hr_status_label)->toBe('Aktif - Belum ada kontrak aktif');
});
