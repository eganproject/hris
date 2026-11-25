<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MobileAttendanceLog;
use App\Models\MobileDevice;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Mobile\MobileAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

const MOBILE_OFFICE_LAT = -6.2000000;
const MOBILE_OFFICE_LNG = 106.8166667;

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-11-25 07:50:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Karyawan Kantor Pusat (absen aplikasi aktif, radius 100 m) dengan shift 08–17.
 *
 * @return array{user: User, employee: Employee, branch: Branch}
 */
function mobileFixture(array $branch = []): array
{
    $branch = Branch::query()->create([
        'code' => 'KP'.Str::random(3), 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true,
        'mobile_attendance_enabled' => true, 'latitude' => MOBILE_OFFICE_LAT, 'longitude' => MOBILE_OFFICE_LNG,
        'geofence_radius_m' => 100, 'geofence_strict' => false,
        ...$branch,
    ]);

    $user = User::factory()->create(['password' => bcrypt('rahasia123')]);
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'branch_id' => $branch->id, 'full_name' => 'Budi Santoso', 'employment_status' => 'active',
    ]);

    $shift = Shift::query()->create([
        'code' => 'REG'.Str::random(3), 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);

    foreach ([0, 1, 2] as $offset) {
        EmployeeSchedule::query()->create([
            'employee_id' => $employee->id, 'work_date' => now()->addDays($offset)->toDateString(),
            'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
        ]);
    }

    return ['user' => $user, 'employee' => $employee->fresh(), 'branch' => $branch];
}

function mobileLogin(User $user, string $deviceId = 'device-aaaa-1111', bool $consent = true): string
{
    $response = test()->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'rahasia123', 'device_id' => $deviceId,
        'platform' => 'android', 'model' => 'Samsung A14', 'os_version' => 'Android 13', 'app_version' => '1.0.0+1',
    ])->assertOk();

    $token = $response->json('data.token');

    if ($consent) {
        test()->withToken($token)->postJson('/api/mobile/v1/auth/consent', ['agree' => true])->assertOk();
    }

    return $token;
}

/**
 * @return array<string, mixed>
 */
function clockPayload(string $type, array $overrides = []): array
{
    return [
        'client_uuid' => (string) Str::uuid(),
        'type' => $type,
        'captured_at' => now()->toIso8601String(),
        'latitude' => MOBILE_OFFICE_LAT + 0.0002, // ±22 m
        'longitude' => MOBILE_OFFICE_LNG,
        'accuracy' => 12,
        'blink_verified' => true,
        'is_mock_location' => false,
        'is_rooted' => false,
        'photo' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
        ...$overrides,
    ];
}

function mobileClock(string $token, string $type, array $overrides = [])
{
    return test()->withToken($token)->post('/api/mobile/v1/attendance/clock', clockPayload($type, $overrides), ['Accept' => 'application/json']);
}

test('HP pertama langsung aktif, login dengan NIK juga bisa, /me menandai atasan', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    Employee::query()->create(['full_name' => 'Bawahan', 'manager_id' => $employee->id, 'employment_status' => 'active']);

    $token = mobileLogin($user);

    $this->withToken($token)->getJson('/api/mobile/v1/me')
        ->assertOk()
        ->assertJsonPath('data.employee.full_name', 'Budi Santoso')
        ->assertJsonPath('data.is_leader', true)
        ->assertJsonPath('data.device.consented', true);

    expect(MobileDevice::query()->where('employee_id', $employee->id)->value('status'))->toBe('active');

    $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $employee->employee_number, 'password' => 'rahasia123', 'device_id' => 'device-aaaa-1111', 'platform' => 'android',
    ])->assertOk();
});

test('kata sandi salah ditolak dan akun nonaktif tidak bisa masuk', function () {
    ['user' => $user] = mobileFixture();

    $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'salah', 'device_id' => 'device-aaaa-1111', 'platform' => 'android',
    ])->assertStatus(422);

    $user->forceFill(['is_active' => false])->save();

    $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'rahasia123', 'device_id' => 'device-aaaa-1111', 'platform' => 'android',
    ])->assertStatus(422);
});

test('HP kedua menunggu HR; setelah disetujui HP lama langsung tidak bisa dipakai', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $oldToken = mobileLogin($user, 'device-lama-0001');

    $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'rahasia123', 'device_id' => 'device-baru-0002', 'platform' => 'ios',
    ])->assertStatus(403)->assertJsonPath('code', 'device_pending');

    $hr = mobileHr();
    $pending = MobileDevice::query()->where('device_uuid', 'device-baru-0002')->firstOrFail();

    $this->actingAs($hr)->get(route('attendance.mobile.devices'))->assertOk()->assertSee('Samsung', false);
    $this->actingAs($hr)->patch(route('attendance.mobile.devices.decide', $pending), ['action' => 'approve'])->assertRedirect();

    expect($pending->fresh()->status)->toBe('active')
        ->and(MobileDevice::query()->where('device_uuid', 'device-lama-0001')->value('status'))->toBe('revoked');

    app('auth')->forgetGuards();
    $this->withToken($oldToken)->getJson('/api/mobile/v1/me')->assertStatus(401);

    app('auth')->forgetGuards();
    $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'rahasia123', 'device_id' => 'device-baru-0002', 'platform' => 'ios',
    ])->assertOk();
});

test('absen butuh persetujuan penyimpanan selfie dan lokasi', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user, consent: false);

    mobileClock($token, 'in')->assertStatus(403)->assertJsonPath('code', 'consent_required');
});

test('absen masuk di dalam radius tercatat ke attendances, lalu terkunci sampai absen pulang', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    mobileClock($token, 'in')
        ->assertOk()
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.within_radius', true)
        ->assertJsonPath('data.needs_review', false);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($attendance->clock_in->format('H:i'))->toBe('07:50')
        ->and($attendance->clock_in_photo_path)->not->toBeNull()
        ->and($attendance->status->value)->toBe('present');
    Storage::disk('local')->assertExists($attendance->clock_in_photo_path);

    $this->withToken($token)->getJson('/api/mobile/v1/attendance/status')
        ->assertOk()
        ->assertJsonPath('data.session.open', true)
        ->assertJsonPath('data.next_action', 'out')
        ->assertJsonPath('data.schedule.shift.start', '08:00');

    Carbon::setTestNow(now()->addMinutes(30));
    mobileClock($token, 'in')->assertOk()
        ->assertJsonPath('data.result', 'rejected')
        ->assertJsonPath('data.reject_code', 'session_open');

    Carbon::setTestNow(Carbon::parse('2026-11-25 17:05:00', 'Asia/Jakarta'));
    mobileClock($token, 'out')->assertOk()->assertJsonPath('data.result', 'accepted');

    $attendance->refresh();
    expect($attendance->clock_out->format('H:i'))->toBe('17:05')
        ->and($attendance->work_minutes)->toBe(9 * 60 + 15 - 60)
        ->and($attendance->clock_out_photo_path)->not->toBeNull();

    $this->withToken($token)->getJson('/api/mobile/v1/attendance/status')->assertJsonPath('data.session.open', false);
    $this->withToken($token)->getJson('/api/mobile/v1/attendance/history?month=2026-11')
        ->assertOk()->assertJsonPath('data.summary.present', 1);
});

test('kiriman ulang dari antrean offline tercatat sekali', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user);
    $uuid = (string) Str::uuid();

    mobileClock($token, 'in', ['client_uuid' => $uuid])->assertJsonPath('data.result', 'accepted');
    mobileClock($token, 'in', ['client_uuid' => $uuid])->assertOk()->assertJsonPath('data.result', 'accepted');

    expect(MobileAttendanceLog::query()->count())->toBe(1);
});

test('absen offline yang terkirim terlambat memakai jam saat tombol ditekan dan ditandai', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    $captured = now()->copy();
    Carbon::setTestNow(now()->addMinutes(40));

    mobileClock($token, 'in', ['captured_at' => $captured->toIso8601String()])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.flags', ['offline_delay']);

    expect(Attendance::query()->where('employee_id', $employee->id)->first()->clock_in->format('H:i'))->toBe('07:50');
});

test('di luar radius tetap tercatat tapi ditandai; mode ketat menolak; Wi-Fi kantor membebaskan', function () {
    ['user' => $user, 'branch' => $branch] = mobileFixture(['wifi_bssids' => ['a4:2b:b0:12:34:56']]);
    $token = mobileLogin($user);
    $far = ['latitude' => MOBILE_OFFICE_LAT + 0.01, 'note' => 'Rapat di kantor klien']; // ±1,1 km

    mobileClock($token, 'in', $far)
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.within_radius', false)
        ->assertJsonPath('data.needs_review', true)
        ->assertJsonPath('data.flags', ['outside_radius']);
    expect(MobileAttendanceLog::query()->latest('id')->value('employee_note'))->toBe('Rapat di kantor klien');

    Carbon::setTestNow(now()->addHours(9));
    mobileClock($token, 'out', [...$far, 'wifi_bssid' => 'A4-2B-B0-12-34-56'])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.wifi_matched', true)
        ->assertJsonPath('data.flags', []);

    $branch->update(['geofence_strict' => true]);
    Carbon::setTestNow(now()->addDay()->setTime(7, 55));
    mobileClock($token, 'in', $far)
        ->assertJsonPath('data.result', 'rejected')
        ->assertJsonPath('data.reject_code', 'outside_radius');
});

test('fake GPS dan HP root ditolak tetapi percobaannya tercatat untuk HR', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    mobileClock($token, 'in', ['is_mock_location' => true])
        ->assertJsonPath('data.result', 'rejected')
        ->assertJsonPath('data.reject_code', 'mock_location');
    mobileClock($token, 'in', ['is_rooted' => true])
        ->assertJsonPath('data.reject_code', 'rooted');

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse()
        ->and(MobileAttendanceLog::query()->where('result', 'rejected')->count())->toBe(2);

    $this->actingAs(mobileHr())->get(route('attendance.mobile.index', ['tab' => 'rejected']))
        ->assertOk()->assertSee('fake GPS', false);
});

test('lokasi yang belum diaktifkan tetap memakai mesin', function () {
    ['user' => $user] = mobileFixture(['mobile_attendance_enabled' => false]);
    $token = mobileLogin($user);

    mobileClock($token, 'in')->assertJsonPath('data.reject_code', 'branch_disabled');
});

test('hari WFH: selfie + lokasi dicatat tanpa radius', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture(['mobile_attendance_enabled' => false]);
    $type = LeaveType::query()->create([
        'code' => 'WFH', 'name' => 'Work From Home', 'attendance_status' => 'wfh',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $type->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        'reason' => 'WFH', 'status' => \App\Enums\LeaveRequestStatus::Approved->value,
    ]);
    $token = mobileLogin($user);

    mobileClock($token, 'in', ['latitude' => -7.25, 'longitude' => 112.75])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.mode', 'wfh')
        ->assertJsonPath('data.distance_m', null)
        ->assertJsonPath('data.flags', []);

    expect(Attendance::query()->where('employee_id', $employee->id)->first()->status->value)->toBe('wfh');
});

test('selfie biasa tanpa cek kedip diterima tanpa tanda', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user);

    $payload = clockPayload('in');
    unset($payload['blink_verified']);

    test()->withToken($token)->post('/api/mobile/v1/attendance/clock', $payload, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.flags', []);

    // APK lama yang masih mengirim blink_verified=false juga tidak ditandai lagi.
    mobileClock($token, 'out', ['blink_verified' => false])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.flags', []);
});

test('absen bertanda masuk antrean tinjauan HR', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user);

    mobileClock($token, 'in', ['latitude' => MOBILE_OFFICE_LAT + 0.01])
        ->assertJsonPath('data.result', 'accepted')
        ->assertJsonPath('data.flags', ['outside_radius']);

    $hr = mobileHr();
    $log = MobileAttendanceLog::query()->firstOrFail();
    $this->actingAs($hr)->get(route('attendance.mobile.index'))->assertOk()->assertSee('Di luar radius kantor');
    $this->actingAs($hr)->patch(route('attendance.mobile.review', $log), ['decision' => 'approved'])->assertRedirect();

    expect($log->fresh()->review_status)->toBe('approved')
        ->and($log->fresh()->reviewed_by)->toBe($hr->id);
});

test('shift malam: absen pulang esok pagi menutup sesi di tanggal absen masuk', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    Carbon::setTestNow(Carbon::parse('2026-11-25 22:00:00', 'Asia/Jakarta'));
    mobileClock($token, 'in')->assertJsonPath('data.result', 'accepted');

    Carbon::setTestNow(Carbon::parse('2026-11-26 06:10:00', 'Asia/Jakarta'));
    mobileClock($token, 'out')->assertJsonPath('data.result', 'accepted')->assertJsonPath('data.work_date', '2026-11-25');

    $row = Attendance::query()->where('employee_id', $employee->id)->sole();
    expect($row->work_date->toDateString())->toBe('2026-11-25')
        ->and($row->clock_out->toDateTimeString())->toBe('2026-11-26 06:10:00');
});

test('kunci sesi hilang setelah batas (default 23j50m) dan bisa diatur di Pengaturan', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user);

    mobileClock($token, 'in');

    Carbon::setTestNow(now()->addMinutes(MobileAttendanceService::DEFAULT_SESSION_MINUTES + 1));
    mobileClock($token, 'out')->assertJsonPath('data.reject_code', 'no_session');
    mobileClock($token, 'in')->assertJsonPath('data.result', 'accepted');

    Setting::set(MobileAttendanceService::SESSION_SETTING_KEY, '60');
    Carbon::setTestNow(now()->addMinutes(61));
    $this->withToken($token)->getJson('/api/mobile/v1/attendance/status')
        ->assertJsonPath('data.session.open', false)
        ->assertJsonPath('data.settings.session_max_minutes', 60);
});

test('HR mengatur absen aplikasi di Lokasi Kerja dan batas sesi di Pengaturan', function () {
    ['branch' => $branch] = mobileFixture();
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['branches.update', 'settings.view', 'settings.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $admin->givePermissionTo(['branches.update', 'settings.view', 'settings.update']);

    $this->actingAs($admin)->put(route('organization.branches.update', $branch), [
        'code' => $branch->code, 'name' => $branch->name, 'type' => 'office', 'is_active' => '1',
        'mobile_attendance_enabled' => '1', 'latitude' => '-6.21', 'longitude' => '106.82',
        'geofence_radius_m' => '150', 'geofence_strict' => '1', 'wifi_bssids' => "A4-2B-B0-12-34-56\nbukan-bssid",
    ])->assertRedirect();

    $branch->refresh();
    expect($branch->geofence_radius_m)->toBe(150)
        ->and($branch->geofence_strict)->toBeTrue()
        ->and($branch->wifi_bssids)->toBe(['a4:2b:b0:12:34:56']);

    $this->actingAs($admin)->put(route('organization.branches.update', $branch), [
        'code' => $branch->code, 'name' => $branch->name, 'type' => 'office', 'mobile_attendance_enabled' => '1',
    ])->assertSessionHasErrors('latitude');

    $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Batas sesi absen aplikasi HP');
    $this->actingAs($admin)->put(route('settings.update'), ['mobile_session_hours' => 20, 'mobile_session_minutes' => 0])->assertRedirect();
    expect(MobileAttendanceService::sessionMaxMinutes())->toBe(1200);
});

test('koreksi absensi bisa diajukan dari aplikasi', function () {
    ['user' => $user] = mobileFixture();
    $token = mobileLogin($user);

    $this->withToken($token)->post('/api/mobile/v1/corrections', [
        'work_date' => now()->subDay()->toDateString(),
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen pulang',
        'attachment' => UploadedFile::fake()->image('bukti.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'pending');

    $this->withToken($token)->getJson('/api/mobile/v1/corrections')->assertOk()->assertJsonCount(1, 'data');
});

function mobileHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['mobile-attendance.view', 'mobile-attendance.update', 'attendance.view.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $hr = User::factory()->create();
    $hr->givePermissionTo(['mobile-attendance.view', 'mobile-attendance.update', 'attendance.view.all']);

    return $hr;
}

test('masuk di mesin lalu pulang lewat HP', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    $employee->punches()->create([
        'punched_at' => '2026-11-25 07:55:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'mesin-masuk',
    ]);
    app(\App\Services\AttendanceRollup::class)->rebuild($employee, Carbon::parse('2026-11-25'));

    Carbon::setTestNow(Carbon::parse('2026-11-25 17:05:00', 'Asia/Jakarta'));

    test()->withToken($token)->getJson('/api/mobile/v1/attendance/status')
        ->assertOk()
        ->assertJsonPath('data.next_action', 'out')
        ->assertJsonPath('data.session.open', true);

    // Masuk lagi lewat HP tetap terkunci karena sesi dari mesin masih terbuka.
    mobileClock($token, 'in')->assertJsonPath('data.result', 'rejected')->assertJsonPath('data.reject_code', 'session_open');

    mobileClock($token, 'out')->assertJsonPath('data.result', 'accepted');

    $attendance = $employee->attendances()->firstOrFail();
    expect($attendance->clock_in->format('H:i'))->toBe('07:55')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:05');

    // Sinkron ulang mesin tidak menghapus jam pulang dari HP.
    app(\App\Services\AttendanceRollup::class)->rebuild($employee, Carbon::parse('2026-11-25'));
    expect($attendance->fresh()->clock_out->format('H:i'))->toBe('17:05');
});

test('masuk lewat HP lalu pulang di mesin menutup sesi', function () {
    ['user' => $user, 'employee' => $employee] = mobileFixture();
    $token = mobileLogin($user);

    mobileClock($token, 'in')->assertJsonPath('data.result', 'accepted');

    $employee->punches()->create([
        'punched_at' => '2026-11-25 17:02:00', 'machine_user_id' => '17', 'status' => 'matched', 'dedup_hash' => 'mesin-pulang',
    ]);
    app(\App\Services\AttendanceRollup::class)->rebuild($employee, Carbon::parse('2026-11-25'));

    $attendance = $employee->attendances()->firstOrFail();
    expect($attendance->clock_in->format('H:i'))->toBe('07:50')
        ->and($attendance->clock_out->format('H:i'))->toBe('17:02');

    Carbon::setTestNow(Carbon::parse('2026-11-25 17:10:00', 'Asia/Jakarta'));

    test()->withToken($token)->getJson('/api/mobile/v1/attendance/status')
        ->assertOk()
        ->assertJsonPath('data.session.open', false);

    // Pulang lewat HP sesudahnya tidak ada sesi lagi.
    mobileClock($token, 'out')->assertJsonPath('data.result', 'rejected')->assertJsonPath('data.reject_code', 'no_session');
});
