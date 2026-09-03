<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function birthdayBranch(string $code, string $name): Branch
{
    return Branch::query()->create([
        'code' => $code, 'name' => $name, 'type' => 'office',
        'city' => 'Surabaya', 'province' => 'Jawa Timur', 'is_active' => true,
    ]);
}

/** Satu jabatan dipakai bersama semua karyawan uji — kodenya unik di tabel. */
function birthdayPosition(): JobPosition
{
    return JobPosition::query()->firstOrCreate(['code' => 'SPV'], [
        'default_role_id' => Role::findOrCreate('employee', 'web')->id,
        'name' => 'Supervisor', 'level' => 'Supervisor', 'is_active' => true,
    ]);
}

/** Karyawan dengan tanggal lahir pada hari ke-$day bulan berjalan, tahun $year. */
function birthdayEmployee(string $name, Branch $branch, int $day, int $year = 1990, array $overrides = []): Employee
{
    return Employee::query()->create([
        'branch_id' => $branch->id,
        'job_position_id' => birthdayPosition()->id,
        'full_name' => $name,
        'employment_status' => 'active',
        'join_date' => now()->subYears(2)->toDateString(),
        'birth_date' => Carbon::create($year, now()->month, $day)->toDateString(),
        ...$overrides,
    ]);
}

function birthdayHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['dashboard.view', 'employees.view', 'employees.view.all'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('the dashboard lists this month birthdays without ever revealing the year', function () {
    $branch = birthdayBranch('SBY', 'Surabaya');
    birthdayEmployee('Budi Ulang Tahun', $branch, 17, 1987);

    $html = $this->actingAs(birthdayHr())->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('Ulang Tahun Bulan Ini')
        ->and($html)->toContain('Budi Ulang Tahun')
        // Tanggal & bulan saja.
        ->and($html)->toContain(Carbon::create(1987, now()->month, 17)->translatedFormat('d M'))
        // Tahun lahirnya tidak boleh bocor ke layar.
        ->and($html)->not->toContain('1987');
});

test('someone born in another month is left out', function () {
    $branch = birthdayBranch('SBY', 'Surabaya');
    birthdayEmployee('Bulan Ini', $branch, 5);

    $lain = Employee::query()->create([
        'branch_id' => $branch->id,
        'job_position_id' => birthdayPosition()->id,
        'full_name' => 'Bulan Lain',
        'employment_status' => 'active',
        'birth_date' => now()->copy()->addMonths(3)->startOfMonth()->addDays(4)->toDateString(),
    ]);

    $html = $this->actingAs(birthdayHr())->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('Bulan Ini')
        ->and($html)->not->toContain($lain->full_name);
});

test('a birthday falling today is called out', function () {
    $branch = birthdayBranch('SBY', 'Surabaya');
    birthdayEmployee('Hari Ini Ultah', $branch, (int) now()->day);

    $this->actingAs(birthdayHr())->get('/dashboard')
        ->assertOk()
        ->assertSee('Hari Ini Ultah')
        ->assertSee('hari ini');
});

test('an employee who has left is not celebrated', function () {
    $branch = birthdayBranch('SBY', 'Surabaya');
    birthdayEmployee('Sudah Keluar', $branch, 12, 1990, ['employment_status' => 'inactive']);

    $this->actingAs(birthdayHr())->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Sudah Keluar');
});

test('the board stays inside the viewer data scope', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['dashboard.view', 'employees.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $mine = birthdayBranch('SBY', 'Surabaya');
    $other = birthdayBranch('JKT', 'Jakarta');

    birthdayEmployee('Rekan Sekantor', $mine, 8);
    birthdayEmployee('Orang Cabang Lain', $other, 9);

    // HR cabang: cakupannya hanya lokasi kerja Surabaya.
    $hr = User::factory()->create();
    $hr->givePermissionTo(['dashboard.view', 'employees.view']);
    $hr->accessBranches()->sync([$mine->id]);

    $this->actingAs($hr)->get('/dashboard')
        ->assertOk()
        ->assertSee('Rekan Sekantor')
        ->assertDontSee('Orang Cabang Lain');
});

test('a plain employee sees the birthdays of their own work location', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('dashboard.view', 'web');

    $mine = birthdayBranch('SBY', 'Surabaya');
    $other = birthdayBranch('JKT', 'Jakarta');

    $user = User::factory()->create();
    $user->givePermissionTo('dashboard.view');

    // Karyawan biasa tidak punya cakupan data karyawan sama sekali; papannya jatuh ke
    // rekan satu lokasi kerja, bukan kosong dan bukan seluruh perusahaan.
    Employee::query()->create([
        'user_id' => $user->id, 'branch_id' => $mine->id,
        'job_position_id' => birthdayPosition()->id,
        'full_name' => 'Saya Sendiri', 'employment_status' => 'active',
        'birth_date' => now()->copy()->startOfMonth()->addDays(2)->toDateString(),
    ]);

    birthdayEmployee('Rekan Sekantor', $mine, 20);
    birthdayEmployee('Orang Cabang Lain', $other, 21);

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('Rekan Sekantor')
        ->and($html)->not->toContain('Orang Cabang Lain')
        // Satu kartu saja, tidak terender dua kali di halaman yang sama.
        ->and(substr_count($html, 'Ulang Tahun Bulan Ini'))->toBe(1);
});

test('an HR account linked to an employee still gets exactly one birthday card', function () {
    $branch = birthdayBranch('SBY', 'Surabaya');
    $hr = birthdayHr();

    Employee::query()->create([
        'user_id' => $hr->id, 'branch_id' => $branch->id,
        'job_position_id' => birthdayPosition()->id,
        'full_name' => 'HR Yang Juga Karyawan', 'employment_status' => 'active',
        'join_date' => now()->subYear()->toDateString(),
    ]);

    birthdayEmployee('Rekan Ultah', $branch, 14);

    $html = $this->actingAs($hr)->get('/dashboard')->assertOk()->getContent();

    expect(substr_count($html, 'Ulang Tahun Bulan Ini'))->toBe(1)
        ->and($html)->toContain('Rekan Ultah');
});

test('the empty state explains itself when nobody has a birthday this month', function () {
    birthdayBranch('SBY', 'Surabaya');

    $this->actingAs(birthdayHr())->get('/dashboard')
        ->assertOk()
        ->assertSee('Ulang Tahun Bulan Ini')
        ->assertSee('Belum ada yang berulang tahun');
});
