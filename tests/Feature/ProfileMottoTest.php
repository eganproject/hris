<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: Employee} */
function mottoOwner(): array
{
    $user = User::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Budi Santoso', 'employment_status' => 'active',
    ]);

    return [$user, $employee];
}

test('the profile page renders with its tabs and the motto field', function () {
    [$user] = mottoOwner();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee('name="motto"', escape: false)
        ->assertSee('data-tab-button="pribadi"', escape: false)
        ->assertSee('data-tab-button="foto"', escape: false)
        ->assertSee('data-tab-button="keamanan"', escape: false);
});

test('an employee writes their own motto and sees it on their profile card', function () {
    [$user, $employee] = mottoOwner();

    $this->actingAs($user)->patch(route('profile.update'), [
        'motto' => 'Kerja rapi, selesai tepat waktu.',
        'phone' => '081234567890',
        'address' => 'Jl. Merdeka 1',
    ])->assertRedirect()->assertSessionHas('status', 'Data pribadi berhasil diperbarui.');

    $employee->refresh();

    expect($employee->motto)->toBe('Kerja rapi, selesai tepat waktu.')
        ->and($employee->phone)->toBe('081234567890');

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Kerja rapi, selesai tepat waktu.');
});

test('the motto is optional and can be cleared again', function () {
    [$user, $employee] = mottoOwner();
    $employee->update(['motto' => 'Motto lama']);

    $this->actingAs($user)->patch(route('profile.update'), ['motto' => ''])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($employee->fresh()->motto)->toBeNull();
});

test('a motto longer than 160 characters is refused, and its tab is reopened', function () {
    [$user, $employee] = mottoOwner();

    $html = $this->actingAs($user)
        ->from(route('profile.edit'))
        ->followingRedirects()
        ->patch(route('profile.update'), ['motto' => str_repeat('a', 161)])
        ->assertOk()
        ->getContent();

    expect($employee->fresh()->motto)->toBeNull()
        // Pesannya berada di dalam tab "Data Pribadi", jadi tab itu harus dipaksa
        // terbuka — kalau tidak, penolakannya tersembunyi dan tampak seperti diam saja.
        ->and($html)->toContain('data-tabs-initial="pribadi"')
        ->and($html)->toContain('motto');
});

test('the motto an employee wrote shows on their detail page for HR', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['dashboard.view', 'employees.view', 'employees.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $hr = User::factory()->create();
    $hr->givePermissionTo(['dashboard.view', 'employees.view', 'employees.view.all']);

    $branch = Branch::query()->create([
        'code' => 'SBY', 'name' => 'Surabaya', 'type' => 'office',
        'city' => 'Surabaya', 'province' => 'Jawa Timur', 'is_active' => true,
    ]);
    $position = JobPosition::query()->create([
        'default_role_id' => Role::findOrCreate('employee', 'web')->id,
        'code' => 'SPV', 'name' => 'Supervisor', 'level' => 'Supervisor', 'is_active' => true,
    ]);

    $employee = Employee::query()->create([
        'branch_id' => $branch->id, 'job_position_id' => $position->id,
        'full_name' => 'Budi Santoso', 'employment_status' => 'active',
        'join_date' => now()->subYear()->toDateString(),
        'motto' => 'Sedikit bicara, banyak kerja.',
    ]);

    $this->actingAs($hr)->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee('Sedikit bicara, banyak kerja.');
});

test('a login account with no employee record still gets a usable profile page', function () {
    // Halaman ini merender kartu identitas dan tab dari data karyawan; tanpa karyawan
    // ia harus tetap terbuka dan tetap menyediakan penggantian password.
    $user = User::factory()->create(['name' => 'Admin Tanpa Karyawan']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Admin Tanpa Karyawan')
        ->assertSee('belum tertaut ke data karyawan')
        ->assertSee('Ubah Password')
        ->assertDontSee('name="motto"', escape: false);
});
