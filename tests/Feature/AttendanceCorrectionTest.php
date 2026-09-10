<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Employee}
 */
function correctionEmployee(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('my-attendance.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('my-attendance.view');
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();
    $employee = Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Budi', 'employment_status' => 'active']);

    return [$user, $employee];
}

function correctionHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $permissions = [...attendanceMenuPermissions(['view', 'update']), 'attendance.view.all'];

    foreach ($permissions as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    // Absensi Harian & Jadwal Kerja dipersempit ke bawahan; pengguna ini
    // mewakili HR/administrator yang dikecualikan lewat Kontrol Akses.
    $user->forceFill(['bypass_team_scope' => true])->save();

    return $user;
}

test('an employee submits an attendance correction for themselves', function () {
    [$user, $employee] = correctionEmployee();

    $this->actingAs($user)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa tap saat pulang.',
        'attachment' => UploadedFile::fake()->image('cctv.jpg'),
    ])->assertRedirect(route('my-attendance.index'));

    expect(AttendanceCorrection::query()->where('employee_id', $employee->id)->where('status', 'pending')->count())->toBe(1);
});

test('a correction with no requested time is rejected', function () {
    [$user] = correctionEmployee();

    $this->actingAs($user)
        ->from(route('my-attendance.index'))
        ->post('/my-attendance/corrections', [
            'work_date' => now()->subDay()->toDateString(),
            'reason' => 'Salah',
            'attachment' => UploadedFile::fake()->image('cctv.jpg'),
        ])
        ->assertSessionHasErrors('requested_clock_in');
});

test('pengajuan tanpa bukti ditolak, dan alasannya menyebut bukti apa yang diminta', function () {
    [$user] = correctionEmployee();

    $this->actingAs($user)
        ->post('/my-attendance/corrections', [
            'work_date' => now()->subDay()->toDateString(),
            'requested_clock_in' => '08:00',
            'reason' => 'Lupa tap.',
        ])
        ->assertSessionHasErrors('attachment');

    expect(AttendanceCorrection::query()->count())->toBe(0)
        ->and(session('errors')->first('attachment'))
        ->toContain('CCTV')
        ->toContain('WFH');
});

test('bukti harus gambar dan tidak boleh lebih dari 2 MB', function () {
    [$user] = correctionEmployee();

    $kirim = fn (UploadedFile $file) => $this->actingAs($user)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa tap.',
        'attachment' => $file,
    ]);

    // PDF hasil pindai bukan yang diminta: yang dicari adalah gambar layar CCTV.
    $kirim(UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))
        ->assertSessionHasErrors('attachment');

    // Tepat di atas batas — dihitung dalam KB seperti aturan validasinya.
    $kirim(UploadedFile::fake()->image('cctv.jpg')->size(2 * 1024 + 1))
        ->assertSessionHasErrors('attachment');

    expect(AttendanceCorrection::query()->count())->toBe(0);

    $kirim(UploadedFile::fake()->image('cctv.jpg')->size(2 * 1024))->assertRedirect();

    expect(AttendanceCorrection::query()->count())->toBe(1);
});

test('bukti tersimpan di disk privat dan hanya bisa dibuka lewat rutenya', function () {
    Storage::fake('local');

    [$user, $employee] = correctionEmployee();

    $this->actingAs($user)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa tap.',
        'attachment' => UploadedFile::fake()->image('cctv.jpg'),
    ])->assertRedirect();

    $correction = AttendanceCorrection::query()->firstOrFail();

    expect($correction->attachment_name)->toBe('cctv.jpg')
        ->and($correction->attachment_path)->toStartWith("correction-attachments/{$employee->id}/")
        // Nama berkas di disk tidak pernah memakai nama dari pengguna.
        ->and($correction->attachment_path)->not->toContain('cctv.jpg');

    Storage::disk('local')->assertExists($correction->attachment_path);

    // Pengajunya sendiri boleh membukanya.
    $this->actingAs($user)->get(route('corrections.attachment', $correction))->assertOk();
});

test('bukti orang lain tidak bisa dibuka tanpa hak meninjau koreksi', function () {
    Storage::fake('local');

    [$user] = correctionEmployee();

    $this->actingAs($user)->post('/my-attendance/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa tap.',
        'attachment' => UploadedFile::fake()->image('cctv.jpg'),
    ])->assertRedirect();

    $correction = AttendanceCorrection::query()->firstOrFail();

    [$orangLain] = correctionEmployee();
    $this->actingAs($orangLain)->get(route('corrections.attachment', $correction))->assertForbidden();

    // Peninjau yang halamannya memang memuat orang itu tetap bisa membukanya.
    $this->actingAs(correctionHr())->get(route('corrections.attachment', $correction))->assertOk();
});

test('HR approves a correction and the attendance is updated', function () {
    [, $employee] = correctionEmployee();
    $hr = correctionHr();

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-02-10',
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa tap',
        'status' => 'pending',
    ]);

    $this->actingAs($hr)->patch(route('attendance.corrections.approve', $correction))->assertRedirect();

    expect($correction->fresh()->status)->toBe('approved')
        ->and($correction->fresh()->reviewed_by)->toBe($hr->id);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->where('work_date', '2026-02-10')->firstOrFail();
    expect($attendance->clock_in->format('H:i'))->toBe('08:00')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:00');
});

test('HR rejects a correction', function () {
    [, $employee] = correctionEmployee();
    $hr = correctionHr();

    $correction = AttendanceCorrection::query()->create(['employee_id' => $employee->id, 'work_date' => '2026-02-10', 'requested_clock_in' => '08:00', 'reason' => 'x', 'status' => 'pending']);

    $this->actingAs($hr)->patch(route('attendance.corrections.reject', $correction), ['decision_notes' => 'Tidak valid'])->assertRedirect();

    expect($correction->fresh()->status)->toBe('rejected')
        ->and($correction->fresh()->decision_notes)->toBe('Tidak valid')
        ->and(Attendance::query()->count())->toBe(0);
});

test('an employee can cancel their own pending correction', function () {
    [$user, $employee] = correctionEmployee();
    $correction = AttendanceCorrection::query()->create(['employee_id' => $employee->id, 'work_date' => '2026-02-10', 'requested_clock_in' => '08:00', 'reason' => 'x', 'status' => 'pending']);

    $this->actingAs($user)->delete(route('my-attendance.corrections.cancel', $correction))->assertRedirect();

    expect(AttendanceCorrection::query()->count())->toBe(0);
});

test('the self-service and review pages render', function () {
    [$user] = correctionEmployee();

    // Formulirnya harus benar-benar bisa mengirim berkas, dan menyebutkan bukti apa
    // yang diminta untuk masing-masing keadaan kerja — kalau tidak, kewajiban barunya
    // hanya muncul sebagai penolakan setelah orangnya menekan Kirim.
    $this->actingAs($user)->get('/my-attendance')
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="attachment"', false)
        ->assertSee('Bukti wajib dilampirkan')
        ->assertSee('rekaman CCTV')
        ->assertSee('WFH atau dinas luar');

    $hr = correctionHr();
    $this->actingAs($hr)->get('/attendance/corrections')->assertOk()->assertSee('Bukti');
});
