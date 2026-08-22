<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function navUser(string ...$permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Pengguna', 'employment_status' => 'active',
    ]);

    return $user;
}

test('an employee gets the self-service shortcuts', function () {
    $user = navUser('dashboard.view', 'my-attendance.view', 'my-leave.view');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('mobile-bottom-nav', escape: false)
        ->assertSee('Beranda')
        ->assertSee('Absensi')
        ->assertSee('Cuti')
        ->assertSee('Lainnya');
});

test('the bar falls back to whatever the user may actually open', function () {
    // Tanpa izin self-service sama sekali: slot terisi menu yang boleh dibuka.
    $user = navUser('attendance-daily.view', 'employees.view');

    $response = $this->actingAs($user)->get(route('attendance.daily.index'))->assertOk();

    $response->assertSee('Harian')->assertSee('Karyawan')->assertSee('Lainnya');

    // Menu yang tidak boleh dibuka tidak muncul sebagai pintasan.
    expect($response->getContent())->not->toContain('>Cuti<');
});

test('it never offers more than five slots', function () {
    // Pengguna serba bisa: kandidatnya lebih banyak daripada slot yang tersedia.
    $user = navUser('dashboard.view', 'my-attendance.view', 'my-leave.view', 'attendance-daily.view', 'employees.view');

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    $slots = substr_count($content, 'mobile-bottom-nav-link');

    // Empat pintasan + tombol Lainnya.
    expect($slots)->toBe(5);
});

test('the current page is marked active in the bar', function () {
    $user = navUser('dashboard.view', 'my-attendance.view');

    $this->actingAs($user)->get(route('my-attendance.index'))
        ->assertOk()
        ->assertSee('mobile-bottom-nav-link is-active', escape: false)
        ->assertSee('aria-current="page"', escape: false);
});

test('the topbar hamburger is gone, replaced by the "Lainnya" button', function () {
    $user = navUser('dashboard.view');

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // Satu-satunya pemicu laci menu sekarang ada di bilah bawah — tidak ada dua tombol
    // untuk hal yang sama.
    expect(substr_count($content, 'data-mobile-nav-toggle'))->toBe(1)
        ->and($content)->toContain('Buka menu lengkap');
});
