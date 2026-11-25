<?php

use App\Models\EmployeeSchedule;
use App\Models\MobileDevice;
use App\Models\Post;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/MobileHelpers.php';

uses(RefreshDatabase::class);

function seenDevice($user, $employee, $at): MobileDevice
{
    return MobileDevice::query()->forceCreate([
        'employee_id' => $employee->id, 'user_id' => $user->id, 'device_uuid' => 'uuid-'.$employee->id,
        'platform' => 'android', 'status' => MobileDevice::STATUS_ACTIVE, 'last_seen_at' => $at,
    ]);
}

test('rekan kerja menampilkan siapa yang online dan offline', function () {
    [$meUser, $me] = mobileEmployee('Ega');
    [$okanUser, $okan] = mobileEmployee('Okan');
    [$sitiUser, $siti] = mobileEmployee('Siti');
    mobileEmployee('Rudi');
    mobileEmployee('Keluar', ['employment_status' => 'resigned']);

    seenDevice($okanUser, $okan, now()->subMinutes(2));
    seenDevice($sitiUser, $siti, now()->subHours(2));

    mobileAs($meUser)->getJson('/api/mobile/v1/people?status=online')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Okan')
        ->assertJsonPath('data.0.online', true)
        ->assertJsonPath('meta.online_count', 1)
        ->assertJsonPath('meta.offline_count', 3);

    $offline = mobileAs($meUser)->getJson('/api/mobile/v1/people?status=offline')->assertOk()->json('data');
    expect(collect($offline)->pluck('name')->all())->toBe(['Siti', 'Ega', 'Rudi'])
        ->and($offline[0]['online'])->toBeFalse()
        ->and($offline[0]['last_seen_at'])->not->toBeNull();

    mobileAs($meUser)->getJson('/api/mobile/v1/people?q=sit')->assertJsonCount(1, 'data');
});

test('profil rekan hanya berisi data publik', function () {
    [$meUser] = mobileEmployee('Ega');
    [, $boss] = mobileEmployee('Andi Atasan');
    [, $okan] = mobileEmployee('Okan', [
        'manager_id' => $boss->id, 'motto' => 'Kerja rapi', 'join_date' => '2022-03-14',
        'phone' => '08123456789', 'address' => 'Jl. Rahasia 1', 'employee_number' => 'NIK-77', 'birth_date' => '1990-01-02',
    ]);

    $json = mobileAs($meUser)->getJson("/api/mobile/v1/people/{$okan->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Okan')
        ->assertJsonPath('data.motto', 'Kerja rapi')
        ->assertJsonPath('data.joined', '2022-03')
        ->assertJsonPath('data.manager.name', 'Andi Atasan')
        ->json('data');

    $raw = json_encode($json);
    foreach (['08123456789', 'Rahasia', 'NIK-77', '1990-01-02', 'phone', 'address', 'email', 'birth_date', 'employee_number'] as $secret) {
        expect($raw)->not->toContain($secret);
    }
});

test('karyawan mengganti dan menghapus foto profil dari HP', function () {
    Storage::fake('public');
    [$user, $me] = mobileEmployee('Ega');

    $url = mobileAs($user)->postJson('/api/mobile/v1/profile/photo', [
        'photo' => UploadedFile::fake()->image('me.jpg', 800, 800),
    ])->assertOk()->json('data.photo_url');

    $path = $me->fresh()->photo_path;
    expect($url)->not->toBeNull()->and($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    mobileAs($user)->postJson('/api/mobile/v1/profile/photo', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable();

    mobileAs($user)->deleteJson('/api/mobile/v1/profile/photo')->assertOk();
    expect($me->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('kabar bisa disaring per penulis', function () {
    [$meUser, $me] = mobileEmployee('Ega');
    [, $okan] = mobileEmployee('Okan');
    Post::query()->create(['employee_id' => $okan->id, 'body' => 'Dari Okan', 'audience' => Post::AUDIENCE_ALL]);
    Post::query()->create(['employee_id' => $me->id, 'body' => 'Dari Ega', 'audience' => Post::AUDIENCE_ALL]);

    mobileAs($meUser)->getJson("/api/mobile/v1/kabar?author={$okan->id}")
        ->assertOk()->assertJsonCount(1, 'data.posts')->assertJsonPath('data.posts.0.body', 'Dari Okan');
});

test('atasan mengatur satu shift untuk beberapa hari sekaligus', function () {
    [$bossUser, $boss] = mobileEmployee('Andi Atasan');
    [, $staff] = mobileEmployee('Dimas', ['manager_id' => $boss->id]);
    $shift = Shift::query()->create(['code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $monday = now()->addWeek()->startOfWeek();
    $dates = collect(range(0, 4))->map(fn ($i) => $monday->copy()->addDays($i)->toDateString())->all();

    mobileAs($bossUser)->putJson('/api/mobile/v1/team/schedule', [
        'employee_id' => $staff->id, 'dates' => $dates, 'shift_id' => $shift->id,
    ])->assertOk()
        ->assertJsonPath('data.members.0.days.4.code', 'P')
        ->assertJsonPath('data.members.0.days.5.code', null);

    expect(EmployeeSchedule::query()->where('employee_id', $staff->id)->count())->toBe(5);

    mobileAs($bossUser)->putJson('/api/mobile/v1/team/schedule', [
        'employee_id' => $staff->id, 'dates' => [now()->subDay()->toDateString()], 'shift_id' => $shift->id,
    ])->assertUnprocessable();
});
