<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function employeeManager(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'dashboard.view',
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function hrMasterData(): array
{
    $branch = Branch::query()->create([
        'code' => 'SBY-OFC-01',
        'name' => 'Surabaya Office 1',
        'type' => 'office',
        'city' => 'Surabaya',
        'province' => 'Jawa Timur',
        'is_active' => true,
    ]);

    $department = Department::query()->create([
        'code' => 'AKR',
        'name' => 'Akrilik & Aksesoris',
        'is_active' => true,
    ]);
    $role = Role::findOrCreate('employee', 'web');

    $position = JobPosition::query()->create([
        'default_role_id' => $role->id,
        'code' => 'SPV',
        'name' => 'Supervisor',
        'level' => 'Supervisor',
        'is_active' => true,
    ]);
    $position->departments()->attach($department->id, ['is_active' => true]);

    $branch->departments()->attach($department->id, ['is_primary' => true, 'is_active' => true]);

    return compact('branch', 'department', 'position');
}

test('employee index shows location and active contract information', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'employee_number' => 'EMP-0100',
        'full_name' => 'Ari Wijaya',
        'email' => 'ari@example.test',
        'join_date' => now()->subMonths(6)->toDateString(),
        'employment_status' => 'active',
    ]);

    $employee->contracts()->create([
        'contract_number' => 'CTR-0100',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get('/employees')
        ->assertSuccessful()
        ->assertSee('Ari Wijaya')
        ->assertSee('Surabaya Office 1')
        ->assertSee('Supervisor')
        ->assertSee('CTR-0100')
        ->assertSee('hari tersisa');
});

test('employee can be created with placement and contract data', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0101',
            'full_name' => 'Nina Kartika',
            'email' => 'nina@example.test',
            'phone' => '08129990001',
            'identity_number' => '3578000000000001',
            'birth_date' => now()->subYears(27)->format('Y-m-d'),
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'address' => 'Surabaya',
            'contract_number' => 'CTR-0101',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'contract_notes' => 'Kontrak tahun pertama.',
            'login_password' => 'Password!2',
            'login_role_id' => null,
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('employee_number', 'EMP-0101')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->branch_id)->toBe($branch->id)
        ->and($employee->contracts()->where('contract_number', 'CTR-0101')->exists())->toBeTrue()
        ->and($employee->user)->not->toBeNull()
        ->and($employee->user->email)->toBe('nina@example.test')
        ->and($employee->user->hasRole('employee'))->toBeTrue();
});

test('employee photo can be uploaded and replaced', function () {
    Storage::fake('public');

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0105',
            'full_name' => 'Dian Pratama',
            'email' => 'dian@example.test',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-0105',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => 'Password!2',
            'photo' => UploadedFile::fake()->image('dian.jpg', 600, 600)->size(512),
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('employee_number', 'EMP-0105')->firstOrFail();
    $oldPhotoPath = $employee->photo_path;

    expect($oldPhotoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($oldPhotoPath);

    $this->actingAs($user)
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0105',
            'full_name' => 'Dian Pratama Updated',
            'email' => 'dian.updated@example.test',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-0105',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => '',
            'photo' => UploadedFile::fake()->image('dian-new.png', 800, 800)->size(640),
        ])
        ->assertRedirect('/employees');

    $employee->refresh();

    expect($employee->photo_path)->not->toBe($oldPhotoPath);
    Storage::disk('public')->assertMissing($oldPhotoPath);
    Storage::disk('public')->assertExists($employee->photo_path);
});

test('employee photo must meet image constraints', function () {
    Storage::fake('public');

    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->from('/employees/create')
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0106',
            'full_name' => 'Eka Lestari',
            'email' => 'eka@example.test',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-0106',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => 'Password!2',
            'photo' => UploadedFile::fake()->image('tiny.jpg', 200, 200)->size(128),
        ])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors('photo');
});

test('employee placement must use department available at selected branch', function () {
    $user = employeeManager();
    ['branch' => $branch, 'position' => $position] = hrMasterData();

    $accounting = Department::query()->create([
        'code' => 'ACC',
        'name' => 'Accounting',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from('/employees/create')
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $accounting->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0102',
            'full_name' => 'Raka Firmansyah',
            'email' => 'raka@example.test',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-0102',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => 'Password!2',
        ])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors(['department_id', 'job_position_id']);
});

test('employee with login account can be updated without changing password', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create([
        'name' => 'Nina Kartika',
        'email' => 'nina@example.test',
        'password' => 'Password!2',
    ]);

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'employee_number' => 'EMP-0103',
        'full_name' => 'Nina Kartika',
        'email' => 'nina@example.test',
        'join_date' => now()->subMonth()->format('Y-m-d'),
        'employment_status' => 'active',
    ]);

    $employee->contracts()->create([
        'contract_number' => 'CTR-0103',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonth()->format('Y-m-d'),
        'end_date' => now()->addYear()->format('Y-m-d'),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->from("/employees/{$employee->id}/edit")
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employee_number' => 'EMP-0103',
            'full_name' => 'Nina Kartika Updated',
            'email' => 'nina.updated@example.test',
            'join_date' => now()->subMonth()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-0103',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->subMonth()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => '',
            'login_role_id' => null,
        ])
        ->assertRedirect('/employees');

    $employee->refresh();
    $loginUser->refresh();

    expect($employee->full_name)->toBe('Nina Kartika Updated')
        ->and($employee->email)->toBe('nina.updated@example.test')
        ->and($loginUser->email)->toBe('nina.updated@example.test');
});

test('employee can be resigned and login account is disabled', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create([
        'name' => 'Rini Santoso',
        'email' => 'rini@example.test',
        'password' => 'Password!2',
    ]);

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'employee_number' => 'EMP-0104',
        'full_name' => 'Rini Santoso',
        'email' => 'rini@example.test',
        'join_date' => now()->subMonths(6)->format('Y-m-d'),
        'employment_status' => 'active',
    ]);

    $contract = $employee->contracts()->create([
        'contract_number' => 'CTR-0104',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(6)->format('Y-m-d'),
        'end_date' => now()->addYear()->format('Y-m-d'),
        'status' => 'active',
    ]);

    $resignedAt = now()->subDay()->format('Y-m-d');

    $this->actingAs($user)
        ->patch("/employees/{$employee->id}/resign", [
            'exit_reason' => 'resigned',
            'exit_date' => $resignedAt,
            'exit_notes' => 'Mengundurkan diri.',
        ])
        ->assertRedirect('/employees');

    $employee->refresh();
    $contract->refresh();
    $loginUser->refresh();

    expect($employee->employment_status)->toBe('inactive')
        ->and($employee->exit_reason)->toBe('resigned')
        ->and($employee->exit_reason_label)->toBe('Mengundurkan Diri')
        ->and($employee->exit_date->format('Y-m-d'))->toBe($resignedAt)
        ->and($employee->exit_notes)->toBe('Mengundurkan diri.')
        ->and($contract->status)->toBe('terminated')
        ->and($contract->end_date->format('Y-m-d'))->toBe($resignedAt)
        ->and($loginUser->is_active)->toBeFalse();

    auth()->guard('web')->logout();

    $this->post('/login', [
        'email' => 'rini@example.test',
        'password' => 'Password!2',
    ])->assertSessionHasErrors('email');
});

test('employee can be marked as terminated for reporting', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'employee_number' => 'EMP-0107',
        'full_name' => 'Tono Prasetyo',
        'join_date' => now()->subMonths(8)->format('Y-m-d'),
        'employment_status' => 'active',
    ]);

    $employee->contracts()->create([
        'contract_number' => 'CTR-0107',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(8)->format('Y-m-d'),
        'end_date' => now()->addMonths(4)->format('Y-m-d'),
        'status' => 'active',
    ]);

    $endedAt = now()->subDay()->format('Y-m-d');

    $this->actingAs($user)
        ->patch("/employees/{$employee->id}/resign", [
            'exit_reason' => 'terminated',
            'exit_date' => $endedAt,
            'exit_notes' => 'Pemutusan hubungan kerja.',
        ])
        ->assertRedirect('/employees');

    $employee->refresh();

    expect($employee->employment_status)->toBe('inactive')
        ->and($employee->employment_status_label)->toBe('Tidak Aktif / Sudah Tidak Bekerja')
        ->and($employee->exit_reason)->toBe('terminated')
        ->and($employee->exit_reason_label)->toBe('PHK')
        ->and($employee->exit_date->format('Y-m-d'))->toBe($endedAt)
        ->and($employee->exit_notes)->toBe('Pemutusan hubungan kerja.');

    $this->actingAs($user)
        ->get('/employees?status=inactive')
        ->assertSuccessful()
        ->assertSee('Tono Prasetyo')
        ->assertSee('PHK');

    $this->actingAs($user)
        ->get('/employees?exit_reason=terminated')
        ->assertSuccessful()
        ->assertSee('Tono Prasetyo')
        ->assertSee('PHK');
});
