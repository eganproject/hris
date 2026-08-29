<?php

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Jejak aktivitas pengguna.
 *
 * Yang dijaga bukan sekadar "ada barisnya", tapi tiga janji yang membuat catatan
 * audit layak dipercaya: pelakunya benar, rahasianya tidak ikut tercatat, dan
 * tindakan yang tidak lewat model tunggal tetap tertangkap.
 */
function activityViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['dashboard.view', 'activity.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create(['name' => 'Pengawas Satu']);
    $user->givePermissionTo(['dashboard.view', 'activity.view']);

    return $user;
}

test('creating a watched record is logged with its actor', function () {
    $user = activityViewer();

    $this->actingAs($user);

    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $log = ActivityLog::query()->where('module', 'shifts')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->actor_name)->toBe('Pengawas Satu')
        ->and($log->subject_id)->toBe($shift->id)
        ->and($log->subject_label)->toBe('REG — Reguler')
        ->and($log->description)->toContain('Menambah');
});

test('an update records what changed, and a no-op update records nothing', function () {
    $user = activityViewer();
    $this->actingAs($user);

    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $shift->update(['name' => 'Reguler Pagi']);

    $log = ActivityLog::query()->where('event', 'updated')->latest('id')->first();

    expect($log->properties['changes']['name'])->toBe(['dari' => 'Reguler', 'jadi' => 'Reguler Pagi']);

    $before = ActivityLog::query()->count();

    // Menyimpan tanpa mengubah apa pun bukan aktivitas.
    $shift->update(['name' => 'Reguler Pagi']);

    expect(ActivityLog::query()->count())->toBe($before);
});

test('archiving is worded differently from deleting outright', function () {
    $user = activityViewer();
    $this->actingAs($user);

    $shift = Shift::query()->create([
        'code' => 'MLM', 'name' => 'Malam',
        'start_time' => '22:00', 'end_time' => '06:00', 'is_active' => true,
    ]);

    $shift->delete();

    expect(ActivityLog::query()->where('event', 'deleted')->latest('id')->value('description'))
        ->toContain('Mengarsipkan');

    $shift->restore();

    expect(ActivityLog::query()->where('event', 'restored')->latest('id')->value('description'))
        ->toContain('Memulihkan');
});

test('a password never reaches the log', function () {
    $user = activityViewer();
    $this->actingAs($user);

    $target = User::factory()->create(['name' => 'Akun Uji']);
    $target->update(['password' => 'RahasiaBaru!9']);

    $log = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    $encoded = json_encode($log->properties);

    expect($log->properties['changes']['password'])->toBe(['dari' => '••••••', 'jadi' => '••••••'])
        ->and($encoded)->not->toContain('RahasiaBaru')
        // Nilai hash-nya pun tidak boleh bocor — itu masih bahan tebak kata sandi.
        ->and($encoded)->not->toContain($target->fresh()->password);
});

test('a successful login is recorded, and a failed one is not blamed on the account owner', function () {
    $user = User::factory()->create(['email' => 'orang@demo.test', 'password' => 'Password!2']);

    $this->post('/login', ['email' => 'orang@demo.test', 'password' => 'salah-sekali'])
        ->assertRedirect();

    $failed = ActivityLog::query()->where('event', 'login_failed')->latest('id')->first();

    expect($failed)->not->toBeNull()
        // Akunnya diketahui, pelakunya tidak — dan itu dua hal berbeda.
        ->and($failed->subject_id)->toBe($user->id)
        ->and($failed->user_id)->toBeNull()
        ->and($failed->actor_name)->toBe('(tidak dikenal)');

    $this->post('/login', ['email' => 'orang@demo.test', 'password' => 'Password!2']);

    $ok = ActivityLog::query()->where('event', 'login')->latest('id')->first();

    expect($ok)->not->toBeNull()
        ->and($ok->user_id)->toBe($user->id);
});

test('a failed login for an unknown email is still recorded', function () {
    $this->post('/login', ['email' => 'entah-siapa@demo.test', 'password' => 'apa-saja']);

    $log = ActivityLog::query()->where('event', 'login_failed')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('tidak terdaftar')
        ->and($log->properties['email'])->toBe('entah-siapa@demo.test');
});

test('a bulk action logs itself, because a mass update fires no model events', function () {
    $user = employeeManager();
    ['branch' => $branch, 'department' => $department, 'position' => $position] = hrMasterData();

    $employee = Employee::query()->create([
        'branch_id' => $branch->id, 'department_id' => $department->id,
        'job_position_id' => $position->id, 'full_name' => 'Kantoran',
        'join_date' => now()->subMonth()->toDateString(), 'employment_status' => 'active',
    ]);

    $this->actingAs($user)
        ->post('/employees/bulk/office-hours', ['employee_ids' => [$employee->id], 'follows' => 1])
        ->assertRedirect('/employees');

    $log = ActivityLog::query()->where('module', 'employees')->latest('id')->first();

    expect($log->description)->toContain('mengikuti jam kantor')
        ->and($log->properties['karyawan'])->toBe(['Kantoran']);
});

test('the activity page is closed without the permission and filters with it', function () {
    $viewer = activityViewer();

    Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $this->actingAs($viewer)->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Aktivitas Pengguna');

    $this->actingAs($viewer)->get(route('activity.index', ['module' => 'shifts']))
        ->assertOk()
        ->assertSee('REG');

    $this->actingAs($viewer)->get(route('activity.index', ['module' => 'leave']))
        ->assertOk()
        ->assertSee('Belum ada aktivitas');

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('activity.index'))->assertForbidden();
});

test('pruning keeps recent entries and drops old ones', function () {
    $this->actingAs(activityViewer());

    Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    ActivityLog::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(200)])->save();

    Shift::query()->create([
        'code' => 'SORE', 'name' => 'Sore',
        'start_time' => '13:00', 'end_time' => '22:00', 'is_active' => true,
    ]);

    // Dibatasi ke modul shift: membuat akun penguji sendiri juga tercatat, dan itu
    // memang seharusnya.
    $shifts = fn () => ActivityLog::query()->where('module', 'shifts');

    expect($shifts()->count())->toBe(2);

    $this->artisan('activity:prune', ['--days' => 180])->assertSuccessful();

    expect($shifts()->count())->toBe(1)
        ->and($shifts()->value('subject_label'))->toBe('SORE — Sore');
});

test('a filter given nonsense does not break the page', function () {
    $viewer = activityViewer();

    // Halaman ini dibuka lewat URL yang gampang disalin-tempel dan disunting tangan.
    // Request::date() melempar pengecualian pada tanggal yang tidak masuk akal, dan
    // Request::string() melempar TypeError begitu parameternya dikirim sebagai array
    // — dua-duanya berujung 500 sebelum penyaringnya dibaca defensif.
    $nonsense = [
        ['from' => 'bukan-tanggal'],
        ['to' => '99-99-9999'],
        ['from' => '2026-13-45'],
        ['from' => ['a' => 'b']],
        ['module' => ['a' => 'b']],
        ['event' => '<script>alert(1)</script>'],
        ['user_id' => 'abc'],
        ['per_page' => '999999'],
        ['search' => str_repeat('%', 200)],
    ];

    foreach ($nonsense as $query) {
        $this->actingAs($viewer)->get(route('activity.index', $query))->assertOk();
    }
});

test('a name longer than the column is trimmed before it is stored', function () {
    $this->actingAs(activityViewer());

    // MySQL berjalan dengan STRICT_TRANS_TABLES: nilai yang melebihi panjang kolom
    // tidak dipotong diam-diam, melainkan MENGGAGALKAN penyimpanan — artinya
    // pencatatan bisa membatalkan tindakan yang sedang dicatatnya.
    Shift::query()->create([
        'code' => str_repeat('K', 50),
        'name' => str_repeat('N', 250),
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $log = ActivityLog::query()->where('module', 'shifts')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and(mb_strlen($log->subject_label))->toBeLessThanOrEqual(255)
        ->and(mb_strlen($log->description))->toBeLessThanOrEqual(500);
});

test('a log stays readable after the account that made it is deleted', function () {
    $actor = activityViewer();
    $this->actingAs($actor);

    Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler',
        'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
    ]);

    $log = ActivityLog::query()->where('module', 'shifts')->latest('id')->first();

    auth()->logout();
    $actor->delete();

    // Justru baris yang pelakunya sudah hilang yang paling perlu terbaca.
    expect($log->fresh()->user_id)->toBeNull()
        ->and($log->fresh()->actor_label)->toBe('Pengawas Satu');
});

test('an action without a session is recorded as the system', function () {
    ActivityLogger::log('schedules', 'generated', 'Roster diperbarui tugas terjadwal.');

    $log = ActivityLog::query()->latest('id')->first();

    expect($log->user_id)->toBeNull()
        ->and($log->actor_label)->toBe('Sistem');
});
