<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * A supervisor (with a login + schedules access, but NOT view.all) over a small
 * org chart. Returns the supervisor's user plus the employees.
 *
 * @return array<string, mixed>
 */
function subordinateFixture(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['schedules.view', 'attendance.view.all', 'attendance-daily.view'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $branch = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $dept = Department::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
    $pos = JobPosition::query()->create(['code' => 'STF', 'name' => 'Staf', 'is_active' => true]);

    $make = fn (string $name, ?int $managerId, ?User $user = null) => Employee::query()->create([
        'user_id' => $user?->id, 'branch_id' => $branch->id, 'department_id' => $dept->id,
        'job_position_id' => $pos->id, 'full_name' => $name, 'manager_id' => $managerId,
        'join_date' => now()->subYear()->toDateString(), 'employment_status' => 'active',
    ]);

    $supervisorUser = User::factory()->create(['limit_to_subordinates' => true]);
    $supervisorUser->givePermissionTo('schedules.view');

    $supervisor = $make('Sari Atasan', null, $supervisorUser);
    $budi = $make('Budi Bawahan', $supervisor->id);          // langsung
    $doni = $make('Doni Cucu', $budi->id);                    // bawahan-nya bawahan
    $lain = $make('Rina Divisi Lain', null);                 // bukan bawahan

    return compact('branch', 'supervisorUser', 'supervisor', 'budi', 'doni', 'lain');
}

test('a subordinate-limited user only sees their reporting subtree', function () {
    $f = subordinateFixture();

    $this->actingAs($f['supervisorUser'])->get('/attendance/schedules')
        ->assertOk()
        ->assertSee('Budi Bawahan')
        ->assertSee('Doni Cucu')                 // berjenjang
        ->assertDontSee('Rina Divisi Lain')      // bukan bawahan
        ->assertDontSee('Sari Atasan');          // dirinya sendiri tidak termasuk
});

test('the subtree is computed from manager_id, not a hardcoded role', function () {
    $f = subordinateFixture();

    expect($f['supervisorUser']->subordinateEmployeeIds())
        ->toEqualCanonicalizing([$f['budi']->id, $f['doni']->id]);

    // Pindahkan Doni keluar dari garis Budi → subtree menyesuaikan otomatis.
    $f['doni']->update(['manager_id' => $f['lain']->id]);

    expect($f['supervisorUser']->fresh()->subordinateEmployeeIds())
        ->toEqualCanonicalizing([$f['budi']->id]);
});

test('a subordinate-limited user cannot open an employee outside their subtree', function () {
    $f = subordinateFixture();

    // Halaman jadwal per karyawan dijaga oleh cakupan yang sama.
    $this->actingAs($f['supervisorUser'])->get("/attendance/schedules/employees/{$f['budi']->id}")->assertOk();
    $this->actingAs($f['supervisorUser'])->get("/attendance/schedules/employees/{$f['lain']->id}")->assertForbidden();
});

test('turning the flag off restores the location/division scope', function () {
    $f = subordinateFixture();

    $user = $f['supervisorUser'];
    $user->forceFill(['limit_to_subordinates' => false])->save();
    // Tanpa cakupan lokasi/divisi & tanpa flag → tidak melihat siapa pun.
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');

    expect($f['budi']->isVisibleTo($user->fresh(), User::SCOPE_BYPASS_ATTENDANCE))->toBeFalse();
});
