<?php

use App\Actions\DeactivateExpiredContracts;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
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

test('the employee code is generated from the join month, branch code and id', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $joinDate = '2026-07-05';

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'full_name' => 'Kode Otomatis',
            'join_date' => $joinDate,
            'employment_status' => 'active',
            'contract_number' => 'CTR-KODE',
            'contract_type' => 'PKWT',
            'contract_start_date' => $joinDate,
            'contract_end_date' => '2027-07-04',
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    // Branch code "SBY-OFC-01" is stripped to SBYOFC01 so it cannot blur into the id.
    $employee = Employee::query()->where('full_name', 'Kode Otomatis')->firstOrFail();

    expect($employee->employee_number)->toBe(sprintf('COK0726-SBYOFC01%04d', $employee->id));
});

test('the employee form shows the code as read-only instead of asking for it', function () {
    $user = employeeManager();
    hrMasterData();

    $this->actingAs($user)
        ->get('/employees/create')
        ->assertOk()
        ->assertSee('Dibuat otomatis setelah disimpan')
        ->assertDontSee('name="employee_number"', escape: false);
});

test('the employee code follows a change of join date or work location', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $jakarta = Branch::query()->create(['code' => 'JKT-OFC-01', 'name' => 'Jakarta Office', 'is_active' => true]);
    $jakarta->departments()->attach($department->id, ['is_primary' => true, 'is_active' => true]);

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Pindah Lokasi',
        'join_date' => '2026-07-05',
        'employment_status' => 'active',
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-PINDAH',
        'contract_type' => 'PKWT',
        'start_date' => '2026-07-05',
        'end_date' => '2027-07-04',
        'status' => 'active',
    ]);

    expect($employee->employee_number)->toBe(sprintf('COK0726-SBYOFC01%04d', $employee->id));

    $this->actingAs($user)
        ->put("/employees/{$employee->id}", [
            'branch_id' => $jakarta->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'full_name' => 'Pindah Lokasi',
            'join_date' => '2026-09-01',
            'employment_status' => 'active',
            'contract_number' => 'CTR-PINDAH',
            'contract_type' => 'PKWT',
            'contract_start_date' => '2026-07-05',
            'contract_end_date' => '2027-07-04',
            'contract_status' => 'active',
            'login_password' => '',
        ])
        ->assertRedirect('/employees');

    expect($employee->fresh()->employee_number)->toBe(sprintf('COK0926-JKTOFC01%04d', $employee->id));
});

test('employee can be created with placement and contract data', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
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

    $employee = Employee::query()->where('full_name', 'Nina Kartika')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->branch_id)->toBe($branch->id)
        ->and($employee->contracts()->where('contract_number', 'CTR-0101')->exists())->toBeTrue()
        ->and($employee->user)->not->toBeNull()
        ->and($employee->user->email)->toBe('nina@example.test')
        ->and($employee->user->hasRole('employee'))->toBeTrue();
});

test('an employee can be given a global machine PIN from the form', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Rudi Absen',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '17']],
            'contract_number' => 'CTR-PIN1',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Rudi Absen')->firstOrFail();

    expect($employee->machine_user_id)->toBe('17')
        ->and($employee->deviceMappings()->whereNull('device_id')->where('machine_user_id', '17')->exists())->toBeTrue();
});

test('creating an employee without a machine PIN is rejected', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->from('/employees/create')
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Tanpa PIN',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-NOPIN',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors('machine_pins');

    expect(Employee::query()->where('full_name', 'Tanpa PIN')->exists())->toBeFalse();
});

test('an employee can be given different PINs on specific machines', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();
    $lobby = Device::query()->create(['serial_number' => 'LOB', 'name' => 'Lobby', 'branch_id' => $branch->id, 'is_active' => true]);
    $plant = Device::query()->create(['serial_number' => 'PLT', 'name' => 'Pabrik', 'branch_id' => $branch->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Multi Mesin',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'machine_pins' => [
                ['device_id' => $lobby->id, 'machine_user_id' => '17'],
                ['device_id' => $plant->id, 'machine_user_id' => '88'],
            ],
            'contract_number' => 'CTR-PIN9',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Multi Mesin')->firstOrFail();

    expect($employee->deviceMappings()->count())->toBe(2)
        ->and($employee->deviceMappings()->where('device_id', $lobby->id)->where('machine_user_id', '17')->exists())->toBeTrue()
        ->and($employee->deviceMappings()->where('device_id', $plant->id)->where('machine_user_id', '88')->exists())->toBeTrue();
});

test('two employees cannot share the same global machine PIN', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $base = [
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'join_date' => now()->format('Y-m-d'),
        'employment_status' => 'active',
        'machine_pins' => [['device_id' => null, 'machine_user_id' => '20']],
        'contract_type' => 'PKWT',
        'contract_start_date' => now()->format('Y-m-d'),
        'contract_end_date' => now()->addYear()->format('Y-m-d'),
        'contract_status' => 'active',
    ];

    $this->actingAs($user)->post('/employees', [...$base, 'full_name' => 'A', 'contract_number' => 'CTR-PIN2'])->assertRedirect('/employees');

    $this->actingAs($user)
        ->post('/employees', [...$base, 'full_name' => 'B', 'contract_number' => 'CTR-PIN3'])
        ->assertSessionHasErrors('machine_pins.0.machine_user_id');
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
            'full_name' => 'Dian Pratama',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
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

    $employee = Employee::query()->where('full_name', 'Dian Pratama')->firstOrFail();
    $oldPhotoPath = $employee->photo_path;

    expect($oldPhotoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($oldPhotoPath);

    $this->actingAs($user)
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Dian Pratama Updated',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
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
            'full_name' => 'Nina Kartika Updated',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
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
        ->and($contract->status)->toBe('ended_early')
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
        ->and($employee->employment_status_label)->toBe('Nonaktif')
        ->and($employee->hr_status_label)->toBe('Tidak Aktif - PHK')
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

test('employee with an expired contract is auto-deactivated', function () {
    employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create(['email' => 'expired@example.test', 'password' => 'Password!2']);

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Kontrak Habis',
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
    ]);

    $expiredEnd = now()->subDays(5)->startOfDay();
    $contract = $employee->contracts()->create([
        'contract_number' => 'CTR-EXP',
        'contract_type' => 'PKWT',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => $expiredEnd->toDateString(),
        'status' => 'active',
    ]);

    // A second employee whose contract has NOT expired yet must stay active.
    $stillActive = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Masih Aktif',
        'join_date' => now()->subMonths(2)->toDateString(),
        'employment_status' => 'active',
    ]);
    $stillActive->contracts()->create([
        'contract_number' => 'CTR-OK',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'status' => 'active',
    ]);

    $count = app(DeactivateExpiredContracts::class)->run();

    $employee->refresh();
    $contract->refresh();
    $loginUser->refresh();
    $stillActive->refresh();

    expect($count)->toBe(1)
        ->and($employee->employment_status)->toBe('inactive')
        ->and($employee->exit_reason)->toBe('contract_ended')
        ->and($employee->exit_date->format('Y-m-d'))->toBe($expiredEnd->format('Y-m-d'))
        ->and($contract->status)->toBe('completed')
        ->and($loginUser->is_active)->toBeFalse()
        ->and($stillActive->employment_status)->toBe('active');
});

test('setting the status to Nonaktif during edit processes the employee exit inline', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create(['email' => 'edit-exit@example.test', 'password' => 'Password!2']);

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Edit Keluar',
        'email' => 'edit-exit@example.test',
        'join_date' => now()->subMonths(6)->toDateString(),
        'employment_status' => 'active',
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-EDX',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
        'status' => 'active',
    ]);

    $exitDate = now()->subDay()->format('Y-m-d');

    $this->actingAs($user)
        ->from("/employees/{$employee->id}/edit")
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Edit Keluar',
            'email' => 'edit-exit@example.test',
            'join_date' => now()->subMonths(6)->format('Y-m-d'),
            // Status Kepegawaian drives the exit: the contract is closed for us.
            'employment_status' => 'inactive',
            'contract_number' => 'CTR-EDX',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->subMonths(6)->format('Y-m-d'),
            'contract_end_date' => now()->addMonths(6)->format('Y-m-d'),
            'contract_status' => 'active',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'login_password' => '',
            'exit_reason' => 'contract_ended',
            'exit_date' => $exitDate,
            'exit_notes' => 'Kontrak selesai.',
        ])
        ->assertRedirect('/employees');

    $employee->refresh();
    $loginUser->refresh();

    expect($employee->employment_status)->toBe('inactive')
        ->and($employee->exit_reason)->toBe('contract_ended')
        ->and($employee->exit_date->format('Y-m-d'))->toBe($exitDate)
        ->and($employee->exit_notes)->toBe('Kontrak selesai.')
        ->and($employee->currentContract)->toBeNull()
        ->and($employee->contracts()->first()->status)->toBe('completed')
        ->and($loginUser->is_active)->toBeFalse();
});

test('setting the status to Nonaktif during edit requires an exit reason', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Tanpa Alasan',
        'join_date' => now()->subMonths(6)->toDateString(),
        'employment_status' => 'active',
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-EDX2',
        'contract_type' => 'PKWT',
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->from("/employees/{$employee->id}/edit")
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Tanpa Alasan',
            'join_date' => now()->subMonths(6)->format('Y-m-d'),
            // Nonaktif without an exit reason / date must not go through.
            'employment_status' => 'inactive',
            'contract_number' => 'CTR-EDX2',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->subMonths(6)->format('Y-m-d'),
            'contract_end_date' => now()->addMonths(6)->format('Y-m-d'),
            'contract_status' => 'active',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'login_password' => '',
        ])
        ->assertRedirect("/employees/{$employee->id}/edit")
        ->assertSessionHasErrors('exit_reason');

    expect($employee->fresh()->employment_status)->toBe('active');
});

test('renewing a contract creates a new active contract and records history', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Perpanjang Kontrak',
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
    ]);
    $old = $employee->contracts()->create([
        'contract_number' => 'CTR-OLD',
        'contract_type' => 'PKWT',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'contract_number' => 'CTR-NEW',
            'contract_type' => 'PKWT',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'notes' => 'Perpanjangan tahun kedua.',
        ])
        ->assertRedirect("/employees/{$employee->id}");

    $employee->refresh();
    $old->refresh();

    expect($employee->contracts()->count())->toBe(2)
        ->and($old->status)->toBe('renewed')
        ->and($employee->currentContract->contract_number)->toBe('CTR-NEW')
        ->and($employee->currentContract->status)->toBe('active')
        ->and($employee->events()->where('type', 'contract_renewed')->exists())->toBeTrue();
});

test('renewing from the employee list redirects back to the list', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Perpanjang Dari List',
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-LIST-OLD',
        'contract_type' => 'PKWT',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'from_list' => '1',
            'contract_number' => 'CTR-LIST-NEW',
            'contract_type' => 'PKWT',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ])
        ->assertRedirect('/employees');

    expect($employee->fresh()->currentContract->contract_number)->toBe('CTR-LIST-NEW');
});

test('a failed renewal flashes the employee so the modal can re-open', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Gagal Validasi',
        'join_date' => now()->subYear()->toDateString(),
        'employment_status' => 'active',
    ]);

    // PKWT without an end date fails validation.
    $this->actingAs($user)
        ->from('/employees')
        ->post("/employees/{$employee->id}/renew-contract", [
            'from_list' => '1',
            'contract_number' => 'CTR-FAIL',
            'contract_type' => 'PKWT',
            'start_date' => now()->format('Y-m-d'),
        ])
        ->assertRedirect('/employees')
        ->assertSessionHasErrors('end_date')
        ->assertSessionHas('renew_employee');
});

test('renewing a contract reactivates an employee who had left', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create(['email' => 'rehire@example.test', 'password' => 'Password!2']);
    $loginUser->forceFill(['is_active' => false])->save();

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Direkrut Ulang',
        'join_date' => now()->subYears(2)->toDateString(),
        'employment_status' => 'inactive',
        'exit_reason' => 'contract_ended',
        'exit_date' => now()->subMonth()->toDateString(),
        'exit_notes' => 'Kontrak selesai.',
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-OLD2',
        'contract_type' => 'PKWT',
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'contract_number' => 'CTR-REHIRE',
            'contract_type' => 'PKWT',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ])
        ->assertRedirect("/employees/{$employee->id}");

    $employee->refresh();
    $loginUser->refresh();

    expect($employee->employment_status)->toBe('active')
        ->and($employee->exit_reason)->toBeNull()
        ->and($employee->exit_date)->toBeNull()
        ->and($employee->currentContract->contract_number)->toBe('CTR-REHIRE')
        ->and($loginUser->is_active)->toBeTrue()
        ->and($employee->events()->where('type', 'reactivated')->exists())->toBeTrue();
});

test('setting the status back to Aktif during edit reactivates a left employee', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $loginUser = User::factory()->create(['email' => 'reedit@example.test', 'password' => 'Password!2']);
    $loginUser->forceFill(['is_active' => false])->save();

    $employee = Employee::query()->create([
        'user_id' => $loginUser->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Aktif Lewat Edit',
        'email' => 'reedit@example.test',
        'join_date' => now()->subYears(2)->toDateString(),
        'employment_status' => 'inactive',
        'exit_reason' => 'contract_ended',
        'exit_date' => now()->subMonth()->toDateString(),
    ]);
    $employee->contracts()->create([
        'contract_number' => 'CTR-REEDIT-OLD',
        'contract_type' => 'PKWT',
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->from("/employees/{$employee->id}/edit")
        ->put("/employees/{$employee->id}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'full_name' => 'Aktif Lewat Edit',
            'email' => 'reedit@example.test',
            'join_date' => now()->subYears(2)->format('Y-m-d'),
            // Back to Aktif: the employee is rehired on the contract entered here.
            'employment_status' => 'active',
            'contract_number' => 'CTR-REEDIT-NEW',
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
            'login_password' => '',
        ])
        ->assertRedirect('/employees');

    $employee->refresh();
    $loginUser->refresh();

    expect($employee->employment_status)->toBe('active')
        ->and($employee->exit_reason)->toBeNull()
        ->and($employee->currentContract?->contract_number)->toBe('CTR-REEDIT-NEW')
        ->and($loginUser->is_active)->toBeTrue()
        ->and($employee->events()->where('type', 'reactivated')->exists())->toBeTrue();
});

test('pkwtt contract does not require an end date but others do', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $basePayload = [
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'job_position_id' => $position->id,
        'full_name' => 'Karyawan Tetap',
        'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
        'join_date' => now()->format('Y-m-d'),
        'employment_status' => 'active',
        'contract_start_date' => now()->format('Y-m-d'),
        'contract_status' => 'active',
    ];

    // PKWT without an end date must fail.
    $this->actingAs($user)
        ->from('/employees/create')
        ->post('/employees', [...$basePayload, 'contract_number' => 'CTR-PKWT', 'contract_type' => 'PKWT'])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors('contract_end_date');

    // PKWTT without an end date must pass.
    $this->actingAs($user)
        ->post('/employees', [...$basePayload, 'contract_number' => 'CTR-PKWTT', 'contract_type' => 'PKWTT'])
        ->assertRedirect('/employees')
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->where('full_name', 'Karyawan Tetap')->firstOrFail();

    expect($employee->currentContract->contract_type)->toBe('PKWTT')
        ->and($employee->currentContract->end_date)->toBeNull();
});

test('creating an employee records joined and contract events', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '1']],
            'full_name' => 'Riwayat Baru',
            'join_date' => now()->format('Y-m-d'),
            'employment_status' => 'active',
            'contract_number' => 'CTR-EVT',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Riwayat Baru')->firstOrFail();

    expect($employee->events()->where('type', 'joined')->exists())->toBeTrue()
        ->and($employee->events()->where('type', 'contract_created')->exists())->toBeTrue();
});
