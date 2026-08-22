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

test('an employee gets the self-service shortcuts, with Absensi Saya as the raised centre button', function () {
    $user = navUser('dashboard.view', 'my-attendance.view', 'my-leave.view');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('mobile-bottom-nav', escape: false)
        ->assertSee('mobile-bottom-nav-fab', escape: false)
        ->assertSee('aria-label="Absensi Saya"', escape: false)
        ->assertSee('Beranda')
        ->assertSee('Absensi')
        ->assertSee('Cuti')
        ->assertSee('Lainnya');
});

test('the centre button sits between two shortcuts on each side', function () {
    $user = navUser('dashboard.view', 'my-attendance.view', 'my-leave.view');

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    $nav = substr($content, strpos($content, '<nav class="mobile-bottom-nav'));
    $nav = substr($nav, 0, strpos($nav, '</nav>'));

    $centre = strpos($nav, 'mobile-bottom-nav-center');

    // Dua pintasan sebelum tombol tengah, dua sesudahnya (yang terakhir "Lainnya").
    expect(substr_count(substr($nav, 0, $centre), 'mobile-bottom-nav-link'))->toBe(2)
        ->and(substr_count(substr($nav, $centre), 'mobile-bottom-nav-link'))->toBe(2);
});

test('the centre button is highlighted while on its own page', function () {
    $user = navUser('dashboard.view', 'my-attendance.view');

    $this->actingAs($user)->get(route('my-attendance.index'))
        ->assertOk()
        ->assertSee('mobile-bottom-nav-fab-link is-active', escape: false);
});

test('without access to Absensi Saya the bar stays flat instead of forcing a centre button', function () {
    $user = navUser('dashboard.view', 'attendance-daily.view', 'employees.view');

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect($content)->not->toContain('mobile-bottom-nav-fab')
        ->and($content)->not->toContain('has-center')
        // Tetap lima slot: empat pintasan datar + Lainnya.
        ->and(substr_count($content, 'mobile-bottom-nav-link'))->toBe(5);
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

    // Tiga pintasan datar + tombol Lainnya, ditambah satu tombol tengah = lima kolom.
    expect(substr_count($content, 'mobile-bottom-nav-link'))->toBe(4)
        ->and(substr_count($content, 'mobile-bottom-nav-fab-link'))->toBe(1);
});

test('the current page is marked active in the bar', function () {
    $user = navUser('dashboard.view', 'my-attendance.view', 'my-leave.view');

    $this->actingAs($user)->get(route('my-leave.index'))
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
