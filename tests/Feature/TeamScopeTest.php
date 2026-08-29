<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Absensi Harian & Jadwal Kerja dipersempit ke bawahan pengguna.
 *
 * Pengecualiannya adalah saklar per pengguna di Kontrol Akses, bukan daftar nama role
 * di dalam kode — susunan role tiap perusahaan berbeda, dan menambah role baru tidak
 * boleh menuntut perubahan kode.
 *
 * Yang paling penting dijaga di sini: pembatasan ini TIDAK boleh merembet ke modul
 * lain. Cuti, lembur, koreksi, laporan, dan data karyawan tetap memakai cakupan
 * lokasi/divisi seperti sebelumnya.
 */
function teamUser(bool $bypass = false): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [...attendanceMenuPermissions(), 'leave.view', User::SCOPE_BYPASS_ATTENDANCE];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $user->forceFill(['bypass_team_scope' => $bypass])->save();

    return $user;
}

/** Atasan (tertaut ke akun) dengan satu bawahan, plus satu karyawan tak terkait. */
function teamTree(User $user): array
{
    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'type' => 'office', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);

    $common = ['branch_id' => $branch->id, 'department_id' => $department->id, 'employment_status' => 'active'];

    $manager = Employee::query()->create([...$common, 'user_id' => $user->id, 'full_name' => 'Rina Atasan']);
    $subordinate = Employee::query()->create([...$common, 'manager_id' => $manager->id, 'full_name' => 'Andi Bawahan']);
    $stranger = Employee::query()->create([...$common, 'full_name' => 'Citra Bukan Bawahan']);

    return [$manager, $subordinate, $stranger];
}

test('the daily board and the roster show only the subordinates', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Andi Bawahan')
            ->assertDontSee('Citra Bukan Bawahan');
    }

    expect($subordinate->id)->not->toBe($stranger->id);
});

test('the switch in Kontrol Akses lifts the restriction', function () {
    $user = teamUser(bypass: true);
    teamTree($user);

    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('Andi Bawahan')
            ->assertSee('Citra Bukan Bawahan');
    }
});

test('a superadmin sees everyone even with the switch off', function () {
    $user = teamUser();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    teamTree($user);

    // Kalau superadmin ikut dibatasi, seorang admin bisa mencabut aksesnya sendiri
    // dan tidak punya jalan kembali untuk memulihkannya.
    $this->actingAs($user)->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Citra Bukan Bawahan');
});

test('the per-employee pages of someone outside the team are forbidden', function () {
    $user = teamUser();
    [, $subordinate, $stranger] = teamTree($user);

    $this->actingAs($user)->get("/attendance/daily/{$subordinate->id}/history")->assertOk();
    $this->actingAs($user)->get("/attendance/daily/{$stranger->id}/history")->assertForbidden();

    $this->actingAs($user)->get("/attendance/schedules/employees/{$subordinate->id}")->assertOk();
    $this->actingAs($user)->get("/attendance/schedules/employees/{$stranger->id}")->assertForbidden();
});

test('a restricted user without any subordinate is told why the page is empty', function () {
    $user = teamUser();

    Employee::query()->create(['full_name' => 'Orang Lain', 'employment_status' => 'active']);
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Rina Tanpa Bawahan', 'employment_status' => 'active']);

    // Halaman kosong tanpa penjelasan terbaca sebagai aplikasi yang rusak.
    foreach (['/attendance/daily', '/attendance/schedules'] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('belum ada seorang pun yang tercatat di bawah Anda', false)
            ->assertDontSee('Orang Lain');
    }
});

test('the restriction does not leak into other modules', function () {
    $user = teamUser();
    [, , $stranger] = teamTree($user);

    // "Belum Terjadwal" memakai cakupan lokasi/divisi seperti sebelumnya, bukan garis
    // atasan — halaman itu tidak termasuk yang diminta untuk dipersempit.
    $this->actingAs($user)->get('/attendance/schedules/unscheduled')
        ->assertOk()
        ->assertSee('Citra Bukan Bawahan');

    // Dan pada tingkat cakupannya sendiri: yang dipersempit hanya forTeam().
    $scoped = DataScope::forAttendance($user->fresh())->employees()->pluck('id');
    $team = DataScope::forTeam($user->fresh())->employees()->pluck('id');

    expect($scoped)->toContain($stranger->id)
        ->and($team)->not->toContain($stranger->id);
});

test('an admin can flip the switch from Kontrol Akses', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['access-control.view', 'access-control.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(['access-control.view', 'access-control.update']);

    $target = teamUser();

    expect($target->fresh()->bypassesTeamScope())->toBeFalse();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), ['bypass_team_scope' => '1'])
        ->assertRedirect();

    expect($target->fresh()->bypassesTeamScope())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('access-control.user-scope.update', $target), [])
        ->assertRedirect();

    expect($target->fresh()->bypassesTeamScope())->toBeFalse();
});
