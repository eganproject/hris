<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * One branch that hosts two divisions, and a Staff position available in both.
 *
 * @return array<string, mixed>
 */
function multiDeptFixture(): array
{
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $acc = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);

    $branch->departments()->attach([$ops->id => ['is_active' => true], $acc->id => ['is_active' => true]]);

    $role = Role::findOrCreate('employee', 'web');
    $position = JobPosition::query()->create(['default_role_id' => $role->id, 'code' => 'STF', 'name' => 'Staf', 'is_active' => true]);
    $position->departments()->attach([$ops->id => ['is_active' => true], $acc->id => ['is_active' => true]]);

    return compact('branch', 'ops', 'acc', 'position');
}

function multiDeptHr(array $extra = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = array_merge([
        'employees.view', 'employees.view.all', 'employees.create', 'employees.update', 'employees.delete', 'employees.export', 'employees.import',
    ], $extra);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('an employee can be created in more than one division', function () {
    $hr = multiDeptHr();
    ['branch' => $branch, 'ops' => $ops, 'acc' => $acc, 'position' => $position] = multiDeptFixture();

    $this->actingAs($hr)->post('/employees', [
        'branch_id' => $branch->id,
        'department_ids' => [$ops->id, $acc->id],
        'job_position_id' => $position->id,
        'machine_pins' => [['device_id' => null, 'machine_user_id' => '10']],
        'full_name' => 'Budi Lintas Divisi',
        'join_date' => now()->toDateString(),
        'employment_status' => 'active',
        'contract_number' => 'CTR-MD',
        'contract_type' => 'PKWT',
        'contract_start_date' => now()->toDateString(),
        'contract_end_date' => now()->addYear()->toDateString(),
        'contract_status' => 'active',
    ])->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Budi Lintas Divisi')->firstOrFail();

    // Kedua divisi tersimpan setara; department_id sekadar salah satunya (yang pertama).
    expect($employee->departments->pluck('id')->sort()->values()->all())->toBe(collect([$ops->id, $acc->id])->sort()->values()->all())
        ->and($employee->department_id)->toBe($ops->id);
});

test('a job position from another of the employee divisions can be assigned', function () {
    $hr = multiDeptHr();
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $acc = Department::query()->create(['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true]);
    $branch->departments()->attach([$ops->id => ['is_active' => true], $acc->id => ['is_active' => true]]);

    // Jabatan "Akuntan" HANYA tersedia di Accounting.
    $akuntan = JobPosition::query()->create(['code' => 'AKN', 'name' => 'Akuntan', 'is_active' => true]);
    $akuntan->departments()->attach([$acc->id => ['is_active' => true]]);

    // Divisi utama Operasional, tapi karyawan juga di Accounting → jabatan Accounting boleh.
    $this->actingAs($hr)->post('/employees', [
        'branch_id' => $branch->id,
        'department_id' => $ops->id,
        'department_ids' => [$acc->id],
        'job_position_id' => $akuntan->id,
        'machine_pins' => [['device_id' => null, 'machine_user_id' => '11']],
        'full_name' => 'Budi Akuntan',
        'join_date' => now()->toDateString(),
        'employment_status' => 'active',
        'contract_number' => 'CTR-AKN',
        'contract_type' => 'PKWT',
        'contract_start_date' => now()->toDateString(),
        'contract_end_date' => now()->addYear()->toDateString(),
        'contract_status' => 'active',
    ])->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Budi Akuntan')->firstOrFail();
    expect($employee->job_position_id)->toBe($akuntan->id);
});

test('a job position not in any of the employee divisions is rejected', function () {
    $hr = multiDeptHr();
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $ops = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $hrDept = Department::query()->create(['code' => 'HRD', 'name' => 'SDM', 'is_active' => true]);
    $branch->departments()->attach([$ops->id => ['is_active' => true]]);

    // Jabatan HRD tidak terkait divisi karyawan (Operasional).
    $hrdPos = JobPosition::query()->create(['code' => 'HRO', 'name' => 'HR Officer', 'is_active' => true]);
    $hrdPos->departments()->attach([$hrDept->id => ['is_active' => true]]);

    $this->actingAs($hr)
        ->from('/employees/create')
        ->post('/employees', [
            'branch_id' => $branch->id,
            'department_id' => $ops->id,
            'job_position_id' => $hrdPos->id,
            'machine_pins' => [['device_id' => null, 'machine_user_id' => '12']],
            'full_name' => 'Salah Jabatan',
            'join_date' => now()->toDateString(),
            'employment_status' => 'active',
            'contract_number' => 'CTR-X',
            'contract_type' => 'PKWT',
            'contract_start_date' => now()->toDateString(),
            'contract_end_date' => now()->addYear()->toDateString(),
            'contract_status' => 'active',
        ])
        ->assertRedirect('/employees/create')
        ->assertSessionHasErrors('job_position_id');
});

test('a scoped HR sees an employee via any of their divisions', function () {
    ['branch' => $branch, 'ops' => $ops, 'acc' => $acc, 'position' => $position] = multiDeptFixture();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $ops->id, 'job_position_id' => $position->id,
        'full_name' => 'Budi Multi', 'join_date' => now()->toDateString(), 'employment_status' => 'active',
    ]);
    $employee->departments()->sync([$ops->id, $acc->id]);

    // HR yang cakupannya HANYA Accounting tetap melihat Budi (divisi utamanya Operasional).
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('employees.view', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_EMPLOYEES, 'web');
    $accHr = User::factory()->create();
    $accHr->givePermissionTo('employees.view');
    $accHr->accessDepartments()->sync([$acc->id]);

    $this->actingAs($accHr)->get('/employees')
        ->assertOk()
        ->assertSee('Budi Multi');

    expect($employee->isVisibleTo($accHr))->toBeTrue();
});

test('the home division is always linked in the pivot even without an explicit sync', function () {
    ['branch' => $branch, 'ops' => $ops, 'position' => $position] = multiDeptFixture();

    // Dibuat langsung (mis. seeder/impor) tanpa menyentuh pivot.
    $employee = Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $ops->id, 'job_position_id' => $position->id,
        'full_name' => 'Seeder Import', 'join_date' => now()->toDateString(), 'employment_status' => 'active',
    ]);

    expect($employee->departments()->pluck('departments.id')->all())->toBe([$ops->id])
        ->and($employee->departmentIds())->toBe([$ops->id]);
});
