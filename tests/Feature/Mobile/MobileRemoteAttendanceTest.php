<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

require_once __DIR__.'/MobileHelpers.php';

/** Karyawan dengan shift reguler hari ini; $wfh menandai harinya WFH di roster. */
function remoteMobileFixture(bool $wfh): array
{
    [$user, $employee] = mobileEmployee('Sari Remote');
    $shift = Shift::query()->create([
        'code' => 'REG', 'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'break_minutes' => 60, 'late_tolerance_minutes' => 10, 'is_active' => true,
    ]);
    EmployeeSchedule::query()->create([
        'employee_id' => $employee->id, 'work_date' => now()->toDateString(),
        'shift_id' => $shift->id, 'is_day_off' => false, 'is_wfh' => $wfh, 'source' => 'generated',
    ]);

    return [$user, $employee];
}

function remoteMobilePayload(string $side): array
{
    return [
        'side' => $side,
        'photo' => UploadedFile::fake()->image('selfie.jpg', 720, 960),
        'latitude' => -6.9147444,
        'longitude' => 107.6098111,
        'accuracy' => 18,
    ];
}

beforeEach(function () {
    Storage::fake(Attendance::SELFIE_DISK);
    $this->travelTo(now()->setTime(8, 3));
});

it('lets a WFH employee clock in and out with a selfie, storing the point without a geofence', function () {
    [$user, $employee] = remoteMobileFixture(true);

    mobileAs($user)->getJson('/api/mobile/v1/attendance/remote')
        ->assertOk()
        ->assertJsonPath('data.allowed', true)
        ->assertJsonPath('data.status', 'wfh')
        ->assertJsonPath('data.next', 'in');

    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('in'), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.next', 'out');

    $a = Attendance::query()->where('employee_id', $employee->id)->sole();
    expect($a->status)->toBe(AttendanceStatus::Wfh)
        ->and($a->clock_in->format('H:i'))->toBe('08:03')
        ->and((float) $a->clock_in_latitude)->toBe(-6.9147444)
        ->and($a->clock_in_accuracy_m)->toBe(18);
    Storage::disk(Attendance::SELFIE_DISK)->assertExists($a->clock_in_photo_path);

    $this->travelTo(now()->setTime(17, 5));
    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('out'), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.next', 'done');

    expect($a->fresh()->clock_out->format('H:i'))->toBe('17:05');
});

it('refuses employees who are not working remotely that day', function () {
    [$user] = remoteMobileFixture(false);

    mobileAs($user)->getJson('/api/mobile/v1/attendance/remote')
        ->assertOk()
        ->assertJsonPath('data.allowed', false)
        ->assertJsonPath('data.next', null);

    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('in'), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Absen mandiri hanya untuk hari WFH atau dinas luar yang sudah disetujui.');

    expect(Attendance::query()->count())->toBe(0);
});

it('rejects a second clock-in and a clock-out before clocking in', function () {
    [$user] = remoteMobileFixture(true);

    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('out'), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Absen masuk dulu sebelum absen pulang.');

    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('in'), ['Accept' => 'application/json'])->assertOk();
    mobileAs($user)->post('/api/mobile/v1/attendance/remote', remoteMobilePayload('in'), ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('requires the selfie and location', function () {
    [$user] = remoteMobileFixture(true);

    mobileAs($user)->postJson('/api/mobile/v1/attendance/remote', ['side' => 'in'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo', 'latitude', 'longitude']);
});
