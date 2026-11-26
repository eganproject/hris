<?php

use App\Models\Attendance;
use App\Models\AttendanceTablet;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\EmployeeSchedule;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\TabletAttendanceLog;
use App\Models\User;
use App\Services\Tablet\TabletSettings;
use App\Support\TabletPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-11-26 07:50:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Kantor Pusat + 1 karyawan shift 08-17 + tablet yang sudah dipasangkan.
 *
 * @return array{branch: Branch, employee: Employee, tablet: AttendanceTablet, token: string}
 */
function tabletFixture(): array
{
    $branch = Branch::query()->create(['code' => 'KP'.Str::random(3), 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true]);
    $employee = Employee::query()->create(['branch_id' => $branch->id, 'full_name' => 'Rina Wulandari', 'employment_status' => 'active']);

    $shift = Shift::query()->create([
        'code' => 'REG'.Str::random(3), 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);

    foreach ([0, 1] as $offset) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => now()->addDays($offset)->toDateString(),
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
    }

    $tablet = AttendanceTablet::query()->create(['name' => 'TAB-KP-01', 'branch_id' => $branch->id, 'status' => 'pending']);
    $code = $tablet->issuePairingCode();

    $token = test()->postJson('/api/tablet/v1/pair', ['code' => strtolower(substr($code, 0, 4)).'-'.substr($code, 4), 'device_model' => 'Samsung Tab A9'])
        ->assertOk()
        ->json('data.token');

    return ['branch' => $branch, 'employee' => $employee->fresh(), 'tablet' => $tablet->fresh(), 'token' => $token];
}

function tabletClock(string $token, Employee $employee, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->withToken($token)->post('/api/tablet/v1/attendance', [
        'client_uuid' => (string) Str::uuid(),
        'employee_id' => $employee->id,
        'captured_at' => now()->toIso8601String(),
        'method' => 'face',
        'match_score' => 0.72,
        'match_margin' => 0.3,
        'liveness_checked' => 1,
        'liveness_score' => 0.97,
        'photo' => UploadedFile::fake()->image('bukti.jpg', 480, 640),
        ...$extra,
    ], ['Accept' => 'application/json']);
}

function tabletEmbedding(int $dim = 512): string
{
    return base64_encode(pack('g*', ...array_fill(0, $dim, 0.01)));
}

function tabletHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['face-tablets.view', 'face-tablets.update', 'attendance.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $hr = User::factory()->create();
    $hr->givePermissionTo(['face-tablets.view', 'face-tablets.update', 'attendance.view.all']);

    return $hr;
}

test('kode pasangan dipakai sekali dan mengaktifkan tablet', function () {
    ['tablet' => $tablet, 'token' => $token] = tabletFixture();

    expect($tablet->status)->toBe('active')
        ->and($tablet->device_model)->toBe('Samsung Tab A9')
        ->and($tablet->pairing_code_hash)->toBeNull()
        ->and($token)->not->toBeEmpty();

    $this->postJson('/api/tablet/v1/pair', ['code' => 'ABCD-EFGH'])->assertStatus(422);
});

test('kode pasangan kedaluwarsa ditolak', function () {
    $branch = Branch::query()->create(['code' => 'KPX', 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true]);
    $tablet = AttendanceTablet::query()->create(['name' => 'TAB', 'branch_id' => $branch->id, 'status' => 'pending']);
    $code = $tablet->issuePairingCode();

    Carbon::setTestNow(now()->addMinutes(AttendanceTablet::PAIRING_MINUTES + 1));

    $this->postJson('/api/tablet/v1/pair', ['code' => $code])->assertStatus(422);
});

test('sinkron berisi karyawan lokasi tablet, data wajah, jadwal, dan pengaturan', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    $other = Branch::query()->create(['code' => 'GD1', 'name' => 'Gudang', 'type' => 'warehouse', 'is_active' => true]);
    Employee::query()->create(['branch_id' => $other->id, 'full_name' => 'Orang Gudang', 'employment_status' => 'active']);

    $profile = EmployeeFaceProfile::query()->firstOrNew(['employee_id' => $employee->id]);
    $profile->forceFill(['embeddings' => [tabletEmbedding()], 'embedding_model' => 'w600k_mbf', 'pin_hash' => TabletPin::hash('123456')])->save();

    Setting::set(TabletSettings::LIVENESS_KEY, '0');

    $data = $this->withToken($token)->getJson('/api/tablet/v1/sync?queue=4')->assertOk()->json('data');

    expect($data['employees'])->toHaveCount(1)
        ->and($data['employees'][0]['name'])->toBe('Rina Wulandari')
        ->and($data['employees'][0]['initials'])->toBe('RW')
        ->and($data['employees'][0]['faces'])->toHaveCount(1)
        ->and($data['employees'][0]['schedule'])->toMatchArray(['start' => '08:00', 'end' => '17:00'])
        ->and($data['settings']['liveness_enabled'])->toBeFalse()
        ->and($data['settings']['match_threshold'])->toBe(0.40)
        ->and($data['settings']['session_max_minutes'])->toBe(1430)
        ->and(TabletPin::verify('123456', $data['employees'][0]['pin_hash']))->toBeTrue()
        ->and(AttendanceTablet::query()->first()->queue_count)->toBe(4);
});

test('pindai pertama = masuk, pindai ulang < 1 menit diabaikan, sore = pulang', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    tabletClock($token, $employee)->assertCreated()->assertJsonPath('data.result', 'accepted')->assertJsonPath('data.type', 'in');

    Carbon::setTestNow(now()->addSeconds(20));
    tabletClock($token, $employee)->assertCreated()->assertJsonPath('data.result', 'ignored');

    Carbon::setTestNow(Carbon::parse('2026-11-26 17:05:00', 'Asia/Jakarta'));
    tabletClock($token, $employee)->assertCreated()->assertJsonPath('data.type', 'out');

    $attendance = Attendance::query()->where('employee_id', $employee->id)->sole();
    expect($attendance->clock_in->format('H:i'))->toBe('07:50')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:05')
        ->and($attendance->clock_in_photo_path)->not->toBeNull()
        ->and($attendance->clock_out_photo_path)->not->toBeNull();

    // Pindai lagi setelah pulang = jam pulang diperbarui dan ditandai.
    Carbon::setTestNow(Carbon::parse('2026-11-26 17:30:00', 'Asia/Jakarta'));
    tabletClock($token, $employee)->assertJsonPath('data.type', 'out')->assertJsonPath('data.flags', ['repeat_out']);
    expect($attendance->fresh()->clock_out->format('H:i'))->toBe('17:30');
});

test('masuk di mesin lalu pulang di tablet', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    app(\App\Services\AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-11-26'), '07:40', null);

    Carbon::setTestNow(Carbon::parse('2026-11-26 17:00:00', 'Asia/Jakarta'));
    tabletClock($token, $employee)->assertJsonPath('data.type', 'out');

    expect(Attendance::query()->sole()->clock_in->format('H:i'))->toBe('07:40');
});

test('tombol pilihan: masuk dan pulang mengikuti pilihan karyawan', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    tabletClock($token, $employee, ['requested_type' => 'in'])->assertCreated()->assertJsonPath('data.type', 'in');

    // Pilih masuk lagi = tidak dicatat ulang.
    Carbon::setTestNow(Carbon::parse('2026-11-26 09:00:00', 'Asia/Jakarta'));
    tabletClock($token, $employee, ['requested_type' => 'in'])->assertJsonPath('data.result', 'ignored')
        ->assertJsonPath('data.reason', 'Sudah absen masuk pukul 07:50.');

    // Pulang cepat (izin) tetap tercatat pulang karena dipilih karyawan.
    Carbon::setTestNow(Carbon::parse('2026-11-26 13:00:00', 'Asia/Jakarta'));
    tabletClock($token, $employee, ['requested_type' => 'out'])->assertJsonPath('data.type', 'out');

    expect(Attendance::query()->sole()->clock_out->format('H:i'))->toBe('13:00');
});

test('tombol pilihan: lupa pulang kemarin, pilih masuk hari ini membuka hari baru', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    // Masuk kemarin 20:00, lupa pulang: sesi masih terbuka pagi ini.
    app(\App\Services\AttendanceResolver::class)->resolve($employee, Carbon::parse('2026-11-25'), '20:00', null);

    tabletClock($token, $employee, ['requested_type' => 'in'])->assertJsonPath('data.type', 'in')
        ->assertJsonPath('data.attendance.work_date', '2026-11-26');

    expect(Attendance::query()->count())->toBe(2);
});

test('tombol pilihan: pulang tanpa absen masuk dicatat dan ditandai', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    Carbon::setTestNow(Carbon::parse('2026-11-26 17:02:00', 'Asia/Jakarta'));
    tabletClock($token, $employee, ['requested_type' => 'out'])->assertJsonPath('data.type', 'out')
        ->assertJsonPath('data.flags', ['no_clock_in']);

    $attendance = Attendance::query()->sole();
    expect($attendance->clock_in)->toBeNull()->and($attendance->clock_out->format('H:i'))->toBe('17:02');
});

test('kirim ulang dari antrean offline dengan UUID sama tidak dobel', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();
    $uuid = (string) Str::uuid();

    tabletClock($token, $employee, ['client_uuid' => $uuid])->assertCreated();
    tabletClock($token, $employee, ['client_uuid' => $uuid])->assertOk()->assertJsonPath('data.type', 'in');

    expect(TabletAttendanceLog::query()->count())->toBe(1);
});

test('absen offline yang terlambat dikirim tetap memakai jam pindai dan ditandai', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    $captured = now()->copy();
    Carbon::setTestNow(now()->addMinutes(40));

    tabletClock($token, $employee, ['captured_at' => $captured->toIso8601String()])
        ->assertJsonPath('data.flags', ['offline_delay']);

    expect(Attendance::query()->sole()->clock_in->format('H:i'))->toBe('07:50');
});

test('NIK + PIN dan skor meragukan ditandai, bukan ditolak', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    tabletClock($token, $employee, ['method' => 'pin', 'match_score' => null, 'liveness_score' => 0.7])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.flags', ['pin_fallback', 'liveness_doubtful']);

    Carbon::setTestNow(Carbon::parse('2026-11-26 17:00:00', 'Asia/Jakarta'));
    tabletClock($token, $employee, ['match_score' => 0.42])->assertJsonPath('data.flags', ['low_match']);
});

test('NIK + PIN ditolak bila dimatikan di pengaturan', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();
    Setting::set(TabletSettings::PIN_KEY, '0');

    tabletClock($token, $employee, ['method' => 'pin'])->assertJsonPath('data.result', 'rejected');
    expect(Attendance::query()->count())->toBe(0);
});

test('"Bukan saya" dicatat tanpa menulis absensi', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();

    tabletClock($token, $employee, ['not_me' => 1])->assertJsonPath('data.result', 'cancelled');

    expect(Attendance::query()->count())->toBe(0)
        ->and(TabletAttendanceLog::query()->sole()->photo_path)->not->toBeNull();
});

test('tablet yang dicabut tidak bisa dipakai lagi', function () {
    ['tablet' => $tablet, 'employee' => $employee, 'token' => $token] = tabletFixture();

    $this->actingAs(tabletHr())->patch(route('attendance.tablets.revoke', $tablet))->assertRedirect();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/tablet/v1/sync')->assertUnauthorized();
});

test('token karyawan/HP tidak bisa memakai API tablet', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile:1')->plainTextToken;

    $this->withToken($token)->getJson('/api/tablet/v1/sync')->assertUnauthorized();
});

test('HR mendaftarkan wajah di tablet dengan PIN HR dan persetujuan', function () {
    ['tablet' => $tablet, 'employee' => $employee, 'token' => $token] = tabletFixture();
    $tablet->forceFill(['hr_pin_hash' => TabletPin::hash('654321')])->save();

    $payload = fn (array $extra = []) => [
        'hr_pin' => '654321', 'employee_id' => $employee->id, 'model' => 'w600k_mbf',
        'embeddings' => [tabletEmbedding(), tabletEmbedding(), tabletEmbedding()],
        'consent' => '1', 'photo' => UploadedFile::fake()->image('depan.jpg'), ...$extra,
    ];

    $this->withToken($token)->post('/api/tablet/v1/faces', $payload(['hr_pin' => '000000']), ['Accept' => 'application/json'])->assertStatus(422);
    $this->withToken($token)->post('/api/tablet/v1/faces', $payload(['consent' => '0']), ['Accept' => 'application/json'])->assertStatus(422);
    $this->withToken($token)->post('/api/tablet/v1/faces', $payload(['embeddings' => ['bukan-base64!!']]), ['Accept' => 'application/json'])->assertStatus(422);
    $this->withToken($token)->post('/api/tablet/v1/faces', $payload(), ['Accept' => 'application/json'])->assertCreated();

    $profile = $employee->faceProfile()->sole();
    expect($profile->embeddings)->toHaveCount(3)
        ->and($profile->embedding_dim)->toBe(512)
        ->and($profile->consented_at)->not->toBeNull()
        ->and($profile->enrolled_tablet_id)->toBe($tablet->id);
});

test('web: daftarkan tablet, atur PIN, PIN karyawan, hapus wajah, pengaturan', function () {
    $hr = tabletHr();
    $branch = Branch::query()->create(['code' => 'KPW', 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true]);
    $employee = Employee::query()->create(['branch_id' => $branch->id, 'full_name' => 'Sari Putri', 'employment_status' => 'active']);

    $this->actingAs($hr)->post(route('attendance.tablets.store'), ['name' => 'TAB-KP-01', 'branch_id' => $branch->id])
        ->assertRedirect(route('attendance.tablets.index'))
        ->assertSessionHas('pairing');

    $tablet = AttendanceTablet::query()->sole();
    $this->put(route('attendance.tablets.hr-pin', $tablet), ['pin' => '123456', 'pin_confirmation' => '123456'])->assertSessionHasNoErrors();
    expect(TabletPin::verify('123456', $tablet->fresh()->hr_pin_hash))->toBeTrue();

    $this->put(route('attendance.tablets.faces.pin', $employee), ['pin' => '12ab'])->assertSessionHasErrors('pin');
    $this->put(route('attendance.tablets.faces.pin', $employee), ['pin' => '111222'])->assertSessionHasNoErrors();
    expect(TabletPin::verify('111222', $employee->faceProfile()->sole()->pin_hash))->toBeTrue();

    $employee->faceProfile->forceFill(['embeddings' => [tabletEmbedding()], 'photo_path' => 'faces/x.jpg'])->save();
    $this->delete(route('attendance.tablets.faces.destroy', $employee))->assertRedirect();
    expect($employee->faceProfile()->sole()->hasFace())->toBeFalse()
        ->and($employee->faceProfile()->sole()->hasPin())->toBeTrue();

    $this->put(route('attendance.tablets.settings'), ['liveness' => '0', 'level' => 'strict', 'pin' => '0'])->assertRedirect();
    expect(TabletSettings::livenessEnabled())->toBeFalse()
        ->and(TabletSettings::matchThreshold())->toBe(0.45)
        ->and(TabletSettings::pinEnabled())->toBeFalse();

    $this->get(route('attendance.tablets.index'))->assertOk()->assertSee('TAB-KP-01')->assertSee('Sari Putri');
    $this->get(route('attendance.tablets.logs'))->assertOk();
    $this->get(route('attendance.tablets.logs', ['tab' => 'flagged']))->assertOk();
});

test('web: riwayat menampilkan absen tablet dan fotonya', function () {
    ['employee' => $employee, 'token' => $token] = tabletFixture();
    tabletClock($token, $employee, ['method' => 'pin'])->assertCreated();
    app('auth')->forgetGuards();

    $log = TabletAttendanceLog::query()->sole();

    $this->actingAs(tabletHr())->get(route('attendance.tablets.logs', ['tab' => 'flagged']))
        ->assertOk()->assertSee('Rina Wulandari')->assertSee('Pakai NIK + PIN');
    $this->get(route('attendance.tablets.logs.photo', $log))->assertOk();
});

test('tanpa izin, menu tablet tertutup', function () {
    $this->actingAs(User::factory()->create())->get(route('attendance.tablets.index'))->assertForbidden();
});
