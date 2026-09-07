<?php

use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\DeviceCommunication;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function pushDevice(): Device
{
    return Device::query()->create([
        'serial_number' => 'SN-TEST-1', 'name' => 'Mesin Lobi', 'is_active' => true,
    ]);
}

test('isi kiriman mesin ikut tersimpan apa adanya', function () {
    $device = pushDevice();
    $employee = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create([
        'employee_id' => $employee->id, 'device_id' => $device->id, 'machine_user_id' => '17',
    ]);

    $body = "17\t2026-02-10 08:05:00\t0\t1\n17\t2026-02-10 17:00:00\t1\t1";

    // Serial mesinnya dibaca dari query string, sama seperti yang dikirim perangkat.
    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
        ->assertOk();

    $log = DeviceCommunication::query()->where('event', 'attlog')->firstOrFail();

    expect($log->payload)->toBe($body)
        ->and($log->payload_bytes)->toBe(strlen($body))
        ->and($log->records_count)->toBe(2)
        ->and($log->isTruncated())->toBeFalse();
});

test('kiriman yang terlalu panjang dipangkas tapi ukuran aslinya tetap tercatat', function () {
    $device = pushDevice();

    $body = str_repeat("17\t2026-02-10 08:05:00\t0\t1\n", 4000);

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $log = DeviceCommunication::query()->where('event', 'attlog')->firstOrFail();

    expect(strlen($log->payload))->toBeLessThan(strlen($body))
        // Ukuran asli tetap jujur, jadi pemangkasan tidak menyamar sebagai kiriman pendek.
        ->and($log->payload_bytes)->toBe(strlen(trim($body)))
        ->and($log->isTruncated())->toBeTrue()
        ->and($log->payload)->toEndWith(DeviceCommunication::TRUNCATION_MARK);
});

test('kiriman tanpa isi tidak menyimpan payload kosong', function () {
    $device = pushDevice();

    $this->get('/iclock/getrequest?SN='.$device->serial_number)->assertOk();

    $log = DeviceCommunication::query()->where('event', 'poll')->firstOrFail();

    expect($log->payload)->toBeNull()
        ->and($log->payload_bytes)->toBeNull();
});

test('isi kiriman dibuang lebih cepat daripada baris ringkasannya', function () {
    $device = pushDevice();

    $baru = $device->communications()->create(['event' => 'attlog', 'records_count' => 1, 'payload' => 'baru', 'payload_bytes' => 4]);
    $tengah = $device->communications()->create(['event' => 'attlog', 'records_count' => 1, 'payload' => 'tengah', 'payload_bytes' => 6]);
    $lama = $device->communications()->create(['event' => 'attlog', 'records_count' => 1, 'payload' => 'lama', 'payload_bytes' => 4]);

    $tengah->forceFill(['created_at' => now()->subDays(5)])->save();
    $lama->forceFill(['created_at' => now()->subDays(20)])->save();

    $this->artisan('devices:prune-communications')->assertExitCode(0);

    expect($baru->fresh()->payload)->toBe('baru')
        // Isinya sudah tidak dibaca lagi, tapi jejak kehadiran mesinnya masih berguna.
        ->and($tengah->fresh()->payload)->toBeNull()
        ->and($tengah->fresh()->records_count)->toBe(1)
        ->and(DeviceCommunication::query()->whereKey($lama->id)->exists())->toBeFalse();
});

test('log komunikasi beserta isinya hanya terbuka bagi yang boleh melihat perangkat', function () {
    $device = pushDevice();
    $device->communications()->create(['event' => 'attlog', 'records_count' => 1, 'payload' => "17\t2026-02-10 08:05:00", 'payload_bytes' => 21]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['devices.view', 'punches.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $boleh = User::factory()->create();
    $boleh->givePermissionTo('devices.view');
    $tidak = User::factory()->create();
    $tidak->givePermissionTo('punches.view');

    $this->actingAs($boleh)->get(route('attendance.devices.monitor'))->assertOk()->assertSee('Isi Kiriman');
    $this->actingAs($tidak)->get(route('attendance.devices.monitor'))->assertForbidden();
});

test('log komunikasi menyembunyikan polling secara bawaan', function () {
    $device = pushDevice();

    $device->communications()->create(['event' => 'attlog', 'records_count' => 2, 'payload' => "17\t2026-02-10 08:05:00", 'payload_bytes' => 21]);
    $device->communications()->create(['event' => 'poll', 'records_count' => 0]);
    $device->communications()->create(['event' => 'handshake', 'records_count' => 0]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('devices.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('devices.view');

    // Bawaan: polling tidak ikut, dan penyaringannya disebutkan supaya tidak terbaca
    // sebagai mesin yang berhenti menyapa.
    $this->actingAs($user)->get(route('attendance.devices.monitor'))
        ->assertOk()
        ->assertSee('Polling disembunyikan')
        ->assertSee('data-event="attlog"', escape: false)
        ->assertSee('data-event="handshake"', escape: false)
        ->assertDontSee('data-event="poll"', escape: false);

    // Masih bisa dibuka semuanya.
    $this->actingAs($user)->get(route('attendance.devices.monitor', ['event' => 'all']))
        ->assertOk()
        ->assertSee('data-event="poll"', escape: false);

    // Dan bisa dipersempit ke satu jenis saja.
    $this->actingAs($user)->get(route('attendance.devices.monitor', ['event' => 'attlog']))
        ->assertOk()
        ->assertSee('data-event="attlog"', escape: false)
        ->assertDontSee('data-event="handshake"', escape: false)
        ->assertDontSee('data-event="poll"', escape: false);
});

test('detail kiriman menguraikan tiap baris beserta punch yang dihasilkannya', function () {
    $device = pushDevice();

    $budi = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create([
        'employee_id' => $budi->id, 'device_id' => $device->id, 'machine_user_id' => '17',
    ]);

    // PIN 17 sudah dipetakan; PIN 99 belum dikenal siapa pun.
    $body = "17\t2026-02-10 08:05:00\t0\t1\n99\t2026-02-10 08:06:00\t0\t1";

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $log = DeviceCommunication::query()->where('event', 'attlog')->firstOrFail();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('devices.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('devices.view');

    $this->actingAs($user)->get(route('attendance.devices.communications.show', $log))
        ->assertOk()
        ->assertSee('Baris Kiriman')
        // Baris yang cocok karyawan tercatat lengkap dengan namanya.
        ->assertSee('Tercatat')
        ->assertSee('Budi')
        // Baris yang PIN-nya belum dipetakan dibedakan, bukan didiamkan.
        ->assertSee('PIN belum dipetakan')
        ->assertSee('2 dari 2 baris tercatat')
        // Isi mentahnya tetap ditampilkan apa adanya.
        ->assertSee('Isi Mentah');
});

test('baris kiriman yang tidak terbaca ditandai, bukan didiamkan', function () {
    $device = pushDevice();

    $log = $device->communications()->create([
        'event' => 'attlog',
        'records_count' => 0,
        'payload' => 'ini bukan baris absensi',
        'payload_bytes' => 23,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('devices.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('devices.view');

    $this->actingAs($user)->get(route('attendance.devices.communications.show', $log))
        ->assertOk()
        ->assertSee('Tidak terbaca');
});

test('detail kiriman ikut terkunci izin melihat perangkat', function () {
    $device = pushDevice();
    $log = $device->communications()->create(['event' => 'poll', 'records_count' => 0]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['devices.view', 'punches.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $tidak = User::factory()->create();
    $tidak->givePermissionTo('punches.view');

    $this->actingAs($tidak)->get(route('attendance.devices.communications.show', $log))->assertForbidden();
});

test('baris X100-C dengan kolom cadangan tetap terbaca utuh', function () {
    $device = pushDevice();

    $budi = Employee::query()->create(['full_name' => 'Budi', 'employment_status' => 'active']);
    EmployeeDevice::query()->create([
        'employee_id' => $budi->id, 'device_id' => $device->id, 'machine_user_id' => '17',
    ]);

    // Bentuk asli kiriman Solution X100-C: sepuluh kolom, enam terakhir cadangan.
    $body = "17\t2026-09-07 09:43:26\t0\t1\t0\t0\t0\t0\t0\t0";

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
        ->assertOk()
        ->assertSee('OK: 1');

    $punch = AttendancePunch::query()->firstOrFail();

    expect($punch->machine_user_id)->toBe('17')
        ->and($punch->punched_at->format('Y-m-d H:i:s'))->toBe('2026-09-07 09:43:26')
        ->and($punch->state)->toBe(0)
        ->and($punch->verify_mode)->toBe(1)
        ->and($punch->status)->toBe(AttendancePunch::STATUS_MATCHED);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('devices.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('devices.view');

    $log = DeviceCommunication::query()->where('event', 'attlog')->firstOrFail();

    // Cara verifikasi diberi nama, bukan ditinggal sebagai angka mentah.
    $this->actingAs($user)->get(route('attendance.devices.communications.show', $log))
        ->assertOk()
        ->assertSee('Sidik jari')
        ->assertSee('Tercatat')
        // State ditampilkan apa adanya, tanpa mengklaim "masuk" atau "pulang".
        ->assertDontSee('menurut mesin')
        ->assertSee('tidak memakainya', escape: false);
});
