<?php

use App\Models\AttendanceTablet;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\EmployeeSchedule;
use App\Models\MobileDevice;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Push\AttendancePushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutDefer();
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-11-26 07:50:00', 'Asia/Jakarta'));

    // Kunci akun layanan palsu (RSA sungguhan supaya tanda tangan JWT jalan).
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($this->credentialsPath, json_encode([
        'type' => 'service_account',
        'project_id' => 'hris-cok-test',
        'client_email' => 'push@hris-cok-test.iam.gserviceaccount.com',
        'private_key' => $pem,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['services.fcm.credentials' => $this->credentialsPath]);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => fn () => $this->fcmResponse ?? Http::response(['name' => 'projects/hris-cok-test/messages/1']),
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    @unlink($this->credentialsPath);
});

/** @return array{0: User, 1: Employee, 2: Branch} */
function pushEmployee(): array
{
    $branch = Branch::query()->create(['code' => 'KP'.Str::random(3), 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Rina']);
    $employee = Employee::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'full_name' => 'Rina Wulandari', 'employment_status' => 'active']);

    $shift = Shift::query()->create([
        'code' => 'REG'.Str::random(3), 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'source' => 'generated',
    ]);

    return [$user, $employee, $branch];
}

function pushPhone(User $user, Employee $employee, ?string $token = 'fcm-token-rina'): MobileDevice
{
    $device = MobileDevice::query()->create([
        'employee_id' => $employee->id, 'user_id' => $user->id, 'device_uuid' => (string) Str::uuid(),
        'platform' => 'android', 'status' => MobileDevice::STATUS_ACTIVE,
    ]);
    $device->forceFill(['push_token' => $token])->save();

    return $device;
}

function pushTabletClock(Branch $branch, Employee $employee): void
{
    $tablet = AttendanceTablet::query()->create(['name' => 'Tablet Lobi', 'branch_id' => $branch->id, 'status' => 'pending']);
    $code = $tablet->issuePairingCode();
    $token = test()->postJson('/api/tablet/v1/pair', ['code' => $code])->assertOk()->json('data.token');

    test()->withToken($token)->post('/api/tablet/v1/attendance', [
        'client_uuid' => (string) Str::uuid(),
        'employee_id' => $employee->id,
        'captured_at' => now()->toIso8601String(),
        'method' => 'face',
        'match_score' => 0.72,
        'match_margin' => 0.3,
        'liveness_checked' => 1,
        'liveness_score' => 0.97,
        'photo' => UploadedFile::fake()->image('bukti.jpg', 480, 640),
    ], ['Accept' => 'application/json'])->assertSuccessful();
}

/** @return list<array<string, mixed>> */
function sentPushes(): array
{
    return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'fcm.googleapis.com'))
        ->map(fn ($pair) => $pair[0]->data()['message'])
        ->values()
        ->all();
}

test('absen masuk di tablet mengirim notifikasi ke HP karyawan dan mencatatnya', function () {
    [$user, $employee, $branch] = pushEmployee();
    pushPhone($user, $employee);

    pushTabletClock($branch, $employee);

    $pushes = sentPushes();
    expect($pushes)->toHaveCount(1)
        ->and($pushes[0]['token'])->toBe('fcm-token-rina')
        ->and($pushes[0]['notification']['title'])->toBe('Absen masuk tercatat')
        ->and($pushes[0]['notification']['body'])->toContain('07:50')->toContain('Tablet Lobi')
        ->and($pushes[0]['data']['category'])->toBe('attendance')
        ->and($pushes[0]['android']['priority'])->toBe('high');

    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'projects/hris-cok-test/messages:send')
        && $r->hasHeader('Authorization', 'Bearer ya29.test'));

    $notification = $user->notifications()->first();
    expect($notification->data['category'])->toBe('attendance')
        ->and($notification->data['meta']['type'])->toBe('in');
});

test('absen pulang tidak dikirim bila dimatikan di pengaturan', function () {
    Setting::set(AttendancePushNotifier::OUT_KEY, '0');
    [$user, $employee, $branch] = pushEmployee();
    pushPhone($user, $employee);

    pushTabletClock($branch, $employee);
    Carbon::setTestNow(now()->addHours(9));
    pushTabletClock($branch, $employee);

    expect(sentPushes())->toHaveCount(1)
        ->and(sentPushes()[0]['notification']['title'])->toBe('Absen masuk tercatat');
});

test('tanpa kunci Firebase, notifikasi tetap tercatat di aplikasi tanpa dikirim', function () {
    config(['services.fcm.credentials' => null]);
    [$user, $employee, $branch] = pushEmployee();
    pushPhone($user, $employee);

    pushTabletClock($branch, $employee);

    expect(sentPushes())->toBeEmpty()
        ->and($user->notifications()->count())->toBe(1);
});

test('token yang ditolak Firebase dibersihkan', function () {
    $this->fcmResponse = Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404);
    [$user, $employee, $branch] = pushEmployee();
    $phone = pushPhone($user, $employee);

    pushTabletClock($branch, $employee);

    expect($phone->fresh()->push_token)->toBeNull();
});

test('tap baru di mesin absensi dikirimi notifikasi, tumpukan tap lama tidak', function () {
    [$user, $employee] = pushEmployee();
    pushPhone($user, $employee);
    $device = Device::query()->create(['serial_number' => 'X100', 'name' => 'Mesin Gudang', 'is_active' => true]);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17']);

    // Tap kemarin yang baru terkirim sekarang: tidak dikabarkan.
    $old = now()->subDay()->setTime(8, 1)->format('Y-m-d H:i:s');
    test()->call('POST', '/iclock/cdata?SN=X100&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "17\t{$old}\t0\t1")->assertOk();
    expect(sentPushes())->toBeEmpty();

    $at = now()->format('Y-m-d H:i:s');
    test()->call('POST', '/iclock/cdata?SN=X100&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "17\t{$at}\t0\t1")->assertOk();

    $pushes = sentPushes();
    expect($pushes)->toHaveCount(1)
        ->and($pushes[0]['notification']['title'])->toBe('Absen masuk tercatat')
        ->and($pushes[0]['notification']['body'])->toContain('Mesin Gudang');

    // Mesin mengirim ulang tap yang sama: tidak ada notifikasi ganda.
    test()->call('POST', '/iclock/cdata?SN=X100&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "17\t{$at}\t0\t1")->assertOk();
    expect(sentPushes())->toHaveCount(1);
});

test('absen dari mesin bisa dikecualikan', function () {
    Setting::set(AttendancePushNotifier::MACHINE_KEY, '0');
    [$user, $employee] = pushEmployee();
    pushPhone($user, $employee);
    $device = Device::query()->create(['serial_number' => 'X100', 'name' => 'Mesin Gudang', 'is_active' => true]);
    EmployeeDevice::query()->create(['employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17']);

    $at = now()->format('Y-m-d H:i:s');
    test()->call('POST', '/iclock/cdata?SN=X100&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], "17\t{$at}\t0\t1")->assertOk();

    expect(sentPushes())->toBeEmpty();
});

test('aplikasi HP mendaftarkan dan melepas token notifikasi', function () {
    [$user, $employee] = pushEmployee();
    $other = pushPhone(User::factory()->create(), $employee, 'token-bersama');

    $login = $this->postJson('/api/mobile/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_id' => 'hp-rina-0001', 'platform' => 'android',
    ])->assertOk();
    $token = $login->json('data.token');

    $this->withToken($token)->postJson('/api/mobile/v1/push-token', ['token' => 'token-bersama'])->assertOk();

    $device = MobileDevice::query()->where('device_uuid', 'hp-rina-0001')->first();
    expect($device->push_token)->toBe('token-bersama')
        ->and($other->fresh()->push_token)->toBeNull();

    $this->withToken($token)->postJson('/api/mobile/v1/auth/logout')->assertOk();
    expect($device->fresh()->push_token)->toBeNull();
});

test('kata-kata notifikasi bisa diubah HR lewat Pengaturan', function () {
    Permission::findOrCreate('settings.view', 'web');
    Permission::findOrCreate('settings.update', 'web');
    $hr = User::factory()->create();
    $hr->givePermissionTo(['settings.view', 'settings.update']);

    $this->actingAs($hr)->get('/settings')->assertOk()->assertSee('Kata-kata notifikasi absen masuk')->assertSee('{jam}');

    $this->actingAs($hr)->put('/settings', [
        'push_settings' => '1',
        'push_attendance_in' => '1',
        'push_attendance_out' => '1',
        'push_attendance_machine' => '1',
        'push_in_title' => 'Selamat pagi, {nama}!',
        'push_in_body' => 'Absen masuk {jam} di {lokasi} ({tanggal}).',
        'push_out_title' => '',
        'push_out_body' => '',
    ])->assertRedirect();
    app('auth')->forgetGuards();

    [$user, $employee, $branch] = pushEmployee();
    pushPhone($user, $employee);
    pushTabletClock($branch, $employee);

    $push = sentPushes()[0];
    expect($push['notification']['title'])->toBe('Selamat pagi, Rina!')
        ->and($push['notification']['body'])->toBe('Absen masuk 07:50 di Tablet Lobi (26 Nov 2026).')
        ->and(AttendancePushNotifier::template('out')['title'])->toBe('Absen pulang tercatat');
});

function pushHr(): User
{
    Permission::findOrCreate('settings.view', 'web');
    Permission::findOrCreate('settings.update', 'web');
    $hr = User::factory()->create();
    $hr->givePermissionTo(['settings.view', 'settings.update']);

    return $hr;
}

test('uji kirim di Pengaturan melaporkan HP yang belum terdaftar', function () {
    [, $employee] = pushEmployee();

    $this->actingAs(pushHr())->from('/settings')->post('/settings/push-test', ['employee_id' => $employee->id])
        ->assertRedirect('/settings')
        ->assertSessionHas('push_test', fn ($r) => $r['ok'] === false && str_contains($r['lines'][0], 'belum terdaftar'));

    expect(sentPushes())->toBeEmpty();
});

test('uji kirim di Pengaturan mengirim notifikasi ke HP karyawan', function () {
    [$user, $employee] = pushEmployee();
    pushPhone($user, $employee);

    $this->actingAs(pushHr())->from('/settings')->post('/settings/push-test', ['employee_id' => $employee->id])
        ->assertSessionHas('push_test', fn ($r) => $r['ok'] === true);

    expect(sentPushes())->toHaveCount(1)
        ->and(sentPushes()[0]['notification']['title'])->toBe('Uji notifikasi HRIS');
});

test('galat isi pesan dari Firebase tidak membuang token HP', function () {
    $this->fcmResponse = Http::response(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Invalid value at message.data']], 400);
    [$user, $employee] = pushEmployee();
    $phone = pushPhone($user, $employee);

    $this->actingAs(pushHr())->from('/settings')->post('/settings/push-test', ['employee_id' => $employee->id])
        ->assertSessionHas('push_test', fn ($r) => $r['ok'] === false && str_contains($r['lines'][0], 'INVALID_ARGUMENT'));

    expect($phone->fresh()->push_token)->toBe('fcm-token-rina');
});
