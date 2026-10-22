<?php

use App\Imports\EmployeesImport;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function superadminRole(): Role
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return Role::findOrCreate('superadmin', 'web');
}

function superadminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(superadminRole());

    return $user;
}

test('HR cannot create a login account with the superadmin role', function () {
    $hr = employeeManager();
    $master = hrMasterData();
    $super = superadminRole();

    $this->actingAs($hr)->post('/employees', contractFormPayload($master, 'Kaki Tangan', 'CTR-ESC-1', [
        'email' => 'kakitangan@example.com',
        'login_password' => 'Rahasia123',
        'login_role_id' => $super->id,
    ]))->assertSessionHasErrors('login_role_id');

    expect(User::query()->where('email', 'kakitangan@example.com')->exists())->toBeFalse();
});

test('HR cannot grant a role holding permissions HR does not have', function () {
    $hr = employeeManager();
    $master = hrMasterData();
    Permission::findOrCreate('access-control.update', 'web');
    $powerful = Role::findOrCreate('akses-admin', 'web');
    $powerful->givePermissionTo('access-control.update');

    $this->actingAs($hr)->post('/employees', contractFormPayload($master, 'Kaki Tangan', 'CTR-ESC-2', [
        'email' => 'kakitangan@example.com',
        'login_password' => 'Rahasia123',
        'login_role_id' => $powerful->id,
    ]))->assertSessionHasErrors('login_role_id');
});

test('HR can still grant a role within their own permissions', function () {
    $hr = employeeManager();
    $master = hrMasterData();

    $this->actingAs($hr)->post('/employees', contractFormPayload($master, 'Karyawan Biasa', 'CTR-ESC-3', [
        'email' => 'biasa@example.com',
        'login_password' => 'Rahasia123',
        'login_role_id' => Role::findOrCreate('employee', 'web')->id,
    ]))->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'biasa@example.com')->firstOrFail()->hasRole('employee'))->toBeTrue();
});

test('the employee form only offers roles the editor may grant', function () {
    $hr = employeeManager();
    hrMasterData();
    superadminRole();

    $html = $this->actingAs($hr)->get('/employees/create')->assertOk()->getContent();

    expect($html)->not->toContain('>superadmin</option>');
});

test('a superadmin may still grant the superadmin role', function () {
    $admin = superadminUser();
    $admin->givePermissionTo(Permission::findOrCreate('employees.create', 'web'), Permission::findOrCreate('employees.view.all', 'web'));
    $master = hrMasterData();

    $this->actingAs($admin)->post('/employees', contractFormPayload($master, 'Admin Baru', 'CTR-ESC-4', [
        'email' => 'adminbaru@example.com',
        'login_password' => 'Rahasia123',
        'login_role_id' => superadminRole()->id,
    ]))->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'adminbaru@example.com')->firstOrFail()->isSuperAdmin())->toBeTrue();
});

test('HR cannot reset the password of a superadmin through the employee form', function () {
    $hr = employeeManager();
    $master = hrMasterData();
    $super = superadminUser();

    $this->actingAs($hr)->post('/employees', contractFormPayload($master, 'Bos Besar', 'CTR-ESC-5'))->assertSessionHasNoErrors();
    $employee = Employee::query()->where('full_name', 'Bos Besar')->firstOrFail();
    $employee->forceFill(['user_id' => $super->id, 'email' => $super->email])->save();
    $hash = $super->password;

    $this->actingAs($hr)->put("/employees/{$employee->id}", contractFormPayload($master, 'Bos Besar', 'CTR-ESC-5', [
        'email' => $super->email,
        'login_password' => 'DiambilAlih1',
    ]))->assertSessionHasErrors('login_password');

    expect($super->fresh()->password)->toBe($hash);
});

test('saving the employee form keeps extra roles set in access control', function () {
    $hr = employeeManager();
    $master = hrMasterData();

    $this->actingAs($hr)->post('/employees', contractFormPayload($master, 'Dua Peran', 'CTR-ESC-6', [
        'email' => 'duaperan@example.com',
        'login_password' => 'Rahasia123',
    ]))->assertSessionHasNoErrors();

    $employee = Employee::query()->where('full_name', 'Dua Peran')->firstOrFail();
    $user = $employee->user;
    $user->assignRole(Role::findOrCreate('employee-reader', 'web'));

    // Form mengirim ulang role pertama yang terpasang, seperti di layar.
    $this->actingAs($hr)->put("/employees/{$employee->id}", contractFormPayload($master, 'Dua Peran', 'CTR-ESC-6', [
        'email' => 'duaperan@example.com',
        'login_role_id' => $user->roles->first()->id,
        'phone' => '0812',
    ]))->assertSessionHasNoErrors();

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['employee', 'employee-reader']);
});

test('the employee import refuses a login role the importer may not grant', function () {
    $hr = employeeManager();
    superadminRole();

    $import = new EmployeesImport(null, null, $hr);
    $import->collection(collect([collect(importRow([
        'email' => 'impor@example.com',
        'password_login' => 'Rahasia123',
        'peran_login' => 'superadmin',
    ]))]));

    expect(implode(' ', $import->errors()))->toContain('tidak berwenang')
        ->and(User::query()->where('email', 'impor@example.com')->exists())->toBeFalse();
});

test('a job position default role cannot be set to superadmin by a non-superadmin', function () {
    $admin = accessAdmin();
    ['position' => $position] = accessEmployeeFixture();

    $this->actingAs($admin)
        ->put(route('access-control.job-positions.update', $position), ['default_role_id' => superadminRole()->id])
        ->assertSessionHasErrors('default_role_id');

    expect($position->fresh()->default_role_id)->not->toBe(superadminRole()->id);
});

test('only a superadmin can grant the superadmin role in access control', function () {
    $admin = accessAdmin();
    $target = User::factory()->create();
    superadminRole();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), ['roles' => ['superadmin']])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $admin), ['roles' => ['superadmin']])
        ->assertForbidden();

    expect($target->fresh()->isSuperAdmin())->toBeFalse()
        ->and($admin->fresh()->isSuperAdmin())->toBeFalse();
});

test('the last active superadmin cannot be demoted', function () {
    $super = superadminUser();
    $super->givePermissionTo(Permission::findOrCreate('access-control.update', 'web'));

    $this->actingAs($super)
        ->put(route('access-control.user-scope.update', $super), ['roles' => []])
        ->assertSessionHas('error');

    expect($super->fresh()->isSuperAdmin())->toBeTrue();
});

test('a deactivated user is logged out on the next request, even via remember me', function () {
    Permission::findOrCreate('my-leave.view', 'web');
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('my-leave.view');

    $this->actingAs($user)->get('/profile')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get('/profile')->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('exiting an employee rotates the remember token', function () {
    $user = User::factory()->create(['remember_token' => 'lama-lama']);
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Keluar', 'employment_status' => 'active']);

    $employee->load('user')->markAsExited('resign', now());

    expect($user->fresh()->remember_token)->not->toBe('lama-lama')
        ->and($user->fresh()->is_active)->toBeFalse();
});

test('an attachment claiming to be HTML is stored and served as what it really is', function () {
    Storage::fake('local');
    Permission::findOrCreate('my-leave.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('my-leave.view');
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Pengunggah', 'employment_status' => 'active']);
    $type = LeaveType::query()->create(['code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'permit', 'is_paid' => true, 'is_active' => true, 'counts_against_balance' => false]);

    $tmp = tempnam(sys_get_temp_dir(), 'att');
    file_put_contents($tmp, "%PDF-1.4\n<html><script>alert(1)</script></html>");

    $this->actingAs($user)->post('/my-leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'attachment' => new UploadedFile($tmp, 'surat.pdf', 'text/html', null, true),
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->firstOrFail();
    expect($leave->attachment_mime)->toBe('application/pdf');

    // Baris lama yang terlanjur menyimpan text/html tidak lagi ditampilkan sebagai HTML.
    $leave->forceFill(['attachment_mime' => 'text/html'])->save();
    $response = $this->actingAs($user)->get("/leave-attachments/{$leave->id}");

    expect($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});
