<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Satu baris absensi WFH beserta foto selfie yang benar-benar ada di disk privat.
 *
 * @return array{user: User, employee: Employee, attendance: Attendance}
 */
function selfieFixture(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-attendance.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('my-attendance.view');
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Pemilik Foto', 'employment_status' => 'active',
    ]);

    $attendance = Attendance::query()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-06-10', 'status' => 'wfh',
        'clock_in' => '2026-06-10 08:00:00',
        'clock_in_photo_path' => 'attendance/selfies/2026/06/rahasia.jpg',
        'clock_in_latitude' => -6.9, 'clock_in_longitude' => 107.6,
    ]);

    Storage::disk('local')->put($attendance->clock_in_photo_path, 'isi foto');

    return compact('user', 'employee', 'attendance');
}

test('the employee can open their own selfie', function () {
    Storage::fake('local');
    ['user' => $user, 'attendance' => $attendance] = selfieFixture();

    $this->actingAs($user)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertOk();
});

test('HR within scope can open it, and can force a download', function () {
    Storage::fake('local');
    ['attendance' => $attendance] = selfieFixture();

    Permission::findOrCreate('attendance-daily.view', 'web');
    Permission::findOrCreate(User::SCOPE_BYPASS_ATTENDANCE, 'web');
    $hr = User::factory()->create();
    $hr->givePermissionTo(['attendance-daily.view', User::SCOPE_BYPASS_ATTENDANCE]);
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $hr->forceFill(['bypass_team_scope' => true])->save();

    $this->actingAs($hr)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertOk();

    $download = $this->actingAs($hr)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in', 'download' => 1]))
        ->assertOk();

    expect($download->headers->get('content-disposition'))->toContain('attachment');
});

test('an unrelated employee and a guest are both refused', function () {
    Storage::fake('local');
    ['attendance' => $attendance] = selfieFixture();

    // Rekan kerja tanpa izin melihat papan absensi.
    $other = User::factory()->create();
    Employee::query()->create([
        'user_id' => $other->id, 'full_name' => 'Orang Lain', 'employment_status' => 'active',
    ]);

    $this->actingAs($other)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertForbidden();

    auth()->logout();
    $this->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertRedirect(route('login'));
});

test('HR outside their data scope is refused', function () {
    Storage::fake('local');
    ['employee' => $employee, 'attendance' => $attendance] = selfieFixture();

    $mine = Branch::query()->create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
    $other = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $employee->update(['branch_id' => $other->id]);

    Permission::findOrCreate('attendance-daily.view', 'web');
    $hrBandung = User::factory()->create();
    $hrBandung->givePermissionTo('attendance-daily.view'); // tanpa izin "semua lokasi"
    $hrBandung->accessBranches()->attach($mine->id);

    $this->actingAs($hrBandung)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertForbidden();
});

test('a missing side, a missing photo and a vanished file all give 404', function () {
    Storage::fake('local');
    ['user' => $user, 'attendance' => $attendance] = selfieFixture();

    // Sisi yang tidak dikenal.
    $this->actingAs($user)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'samping']))
        ->assertNotFound();

    // Sisi pulang belum ada fotonya.
    $this->actingAs($user)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'out']))
        ->assertNotFound();

    // Baris masih menunjuk berkas yang sudah dipangkas dari disk.
    Storage::disk('local')->delete($attendance->clock_in_photo_path);
    $this->actingAs($user)
        ->get(route('attendance.selfie', ['attendance' => $attendance, 'side' => 'in']))
        ->assertNotFound();
});

test('the superadmin dry-run photo is private and only its own owner may see it', function () {
    Storage::fake('local');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-attendance.view', 'web');
    $role = Role::findOrCreate('superadmin', 'web');
    $role->givePermissionTo('my-attendance.view');

    $admin = User::factory()->create();
    $admin->assignRole($role);
    Employee::query()->create(['user_id' => $admin->id, 'full_name' => 'Super', 'employment_status' => 'active']);

    $this->actingAs($admin)->post('/my-attendance/selfie-test', [
        'photo' => UploadedFile::fake()->image('uji.jpg'),
        'latitude' => -6.9, 'longitude' => 107.6, 'accuracy' => 10,
    ])->assertRedirect();

    // Tersimpan di disk privat, bukan publik.
    Storage::disk('local')->assertExists('attendance/selfie-tests/'.$admin->id.'.jpg');

    $this->actingAs($admin)->get(route('my-attendance.selfie-test.show'))->assertOk();

    // Bukan superadmin: ditolak, walau tahu alamatnya.
    $biasa = User::factory()->create();
    $biasa->givePermissionTo('my-attendance.view');
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $biasa->forceFill(['bypass_team_scope' => true])->save();
    $this->actingAs($biasa)->get(route('my-attendance.selfie-test.show'))->assertForbidden();
});
