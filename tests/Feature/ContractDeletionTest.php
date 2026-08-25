<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Hapus kontrak disediakan untuk membersihkan baris duplikat — hasil salah input
 * atau reaktivasi yang menghasilkan kontrak kedua. Pagarnya menjaga satu hal:
 * jangan sampai ada karyawan aktif yang kehilangan seluruh kontraknya, karena
 * karyawan seperti itu tidak akan pernah dinonaktifkan otomatis.
 */
function contractHr(string ...$extra): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['employees.view', 'employees.delete', 'employees.view.all', ...$extra];

    foreach ($permissions as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function contractEmployee(string $status = 'active'): Employee
{
    $branch = Branch::query()->firstOrCreate(['code' => 'HO'], ['name' => 'Pusat', 'is_active' => true]);

    return Employee::query()->create([
        'full_name' => 'Budi', 'employment_status' => $status,
        'join_date' => '2025-01-01', 'branch_id' => $branch->id,
    ]);
}

function deletableContractFor(Employee $employee, string $number, string $status = 'active'): EmployeeContract
{
    return $employee->contracts()->create([
        'contract_number' => $number, 'contract_type' => 'PKWT',
        'start_date' => '2025-01-01', 'end_date' => '2026-12-31', 'status' => $status,
    ]);
}

test('a duplicate contract can be deleted and the removal is recorded', function () {
    $employee = contractEmployee();
    $asli = deletableContractFor($employee, 'KTR-001');
    $duplikat = deletableContractFor($employee, 'KTR-001-DUP', 'cancelled');

    $this->actingAs(contractHr())
        ->from(route('employees.contracts.index'))
        ->delete(route('employees.contracts.destroy', $duplikat))
        ->assertRedirect(route('employees.contracts.index'));

    expect(EmployeeContract::query()->pluck('id')->all())->toBe([$asli->id]);

    // Jejaknya tetap ada meski barisnya hilang.
    $event = EmployeeEvent::query()->where('type', 'contract_deleted')->firstOrFail();
    expect($event->employee_id)->toBe($employee->id)
        ->and($event->description)->toContain('KTR-001-DUP')
        ->and($event->properties['contract_number'])->toBe('KTR-001-DUP');
});

test('the only contract of an employee is never deletable', function () {
    $employee = contractEmployee();
    $satunya = deletableContractFor($employee, 'KTR-001');

    expect($satunya->isDeletable())->toBeFalse();

    $this->actingAs(contractHr())
        ->from(route('employees.contracts.index'))
        ->delete(route('employees.contracts.destroy', $satunya))
        ->assertRedirect(route('employees.contracts.index'))
        ->assertSessionHas('error');

    expect(EmployeeContract::query()->count())->toBe(1);
});

test('an active employee cannot lose their last active contract', function () {
    $employee = contractEmployee();
    deletableContractFor($employee, 'KTR-LAMA', 'completed');
    $aktif = deletableContractFor($employee, 'KTR-BARU');

    expect($aktif->fresh()->isDeletable())->toBeFalse();

    $this->actingAs(contractHr())
        ->from(route('employees.contracts.index'))
        ->delete(route('employees.contracts.destroy', $aktif))
        ->assertSessionHas('error');

    expect(EmployeeContract::query()->count())->toBe(2);
});

test('an inactive employee may lose an active contract row, since nothing hangs', function () {
    $employee = contractEmployee('inactive');
    deletableContractFor($employee, 'KTR-LAMA', 'completed');
    $nyasar = deletableContractFor($employee, 'KTR-NYASAR');

    expect($nyasar->fresh()->isDeletable())->toBeTrue();

    $this->actingAs(contractHr())
        ->from(route('employees.contracts.index'))
        ->delete(route('employees.contracts.destroy', $nyasar))
        ->assertSessionHas('status');

    expect(EmployeeContract::query()->count())->toBe(1);
});

test('deleting a contract outside the users scope is refused', function () {
    $employee = contractEmployee();
    deletableContractFor($employee, 'KTR-001');
    $duplikat = deletableContractFor($employee, 'KTR-002', 'cancelled');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['employees.view', 'employees.delete'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    // Tanpa employees.view.all dan tanpa cakupan apa pun: tidak melihat siapa-siapa.
    $sempit = User::factory()->create();
    $sempit->givePermissionTo(['employees.view', 'employees.delete']);

    $this->actingAs($sempit)
        ->delete(route('employees.contracts.destroy', $duplikat))
        ->assertForbidden();

    expect(EmployeeContract::query()->count())->toBe(2);
});

test('without employees.delete the action is not offered at all', function () {
    $employee = contractEmployee();
    deletableContractFor($employee, 'KTR-001');
    $duplikat = deletableContractFor($employee, 'KTR-002', 'cancelled');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['employees.view', 'employees.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $pembaca = User::factory()->create();
    $pembaca->givePermissionTo(['employees.view', 'employees.view.all']);

    $this->actingAs($pembaca)->get(route('employees.contracts.index'))
        ->assertOk()
        ->assertDontSee(route('employees.contracts.destroy', $duplikat), false);

    $this->actingAs($pembaca)->delete(route('employees.contracts.destroy', $duplikat))
        ->assertForbidden();
});
