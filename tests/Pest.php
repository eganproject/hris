<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Permission untuk seluruh menu absensi/jadwal/cuti + laporan — pengganti
 * "attendance.*" lama, yang dulu satu permission membuka semua menu itu sekaligus.
 * Dipakai helper "HR pusat" di berbagai test.
 *
 * @param  list<string>  $actions
 * @return list<string>
 */
function attendanceMenuPermissions(array $actions = ['view', 'create', 'update', 'delete']): array
{
    $menus = [
        'attendance-daily', 'punches', 'corrections', 'overtime', 'swaps',
        'devices', 'shifts', 'holidays', 'schedule-patterns', 'schedules',
        'leave', 'leave-types', 'leave-balances',
        'reports.attendance', 'reports.log', 'reports.leave',
    ];

    $catalog = collect(config('rbac.menus'))->collapse();
    $permissions = [];

    foreach ($menus as $menu) {
        foreach ($catalog->get($menu)['actions'] ?? [] as $action) {
            // "export" hanya relevan untuk laporan, dan ikut aksi "view" pemanggilnya.
            $wanted = $action === 'export' ? 'view' : $action;

            if (in_array($wanted, $actions, true)) {
                $permissions[] = $menu.'.'.$action;
            }
        }
    }

    if (in_array('update', $actions, true)) {
        $permissions[] = 'settings.view';
        $permissions[] = 'settings.update';
    }

    return $permissions;
}
