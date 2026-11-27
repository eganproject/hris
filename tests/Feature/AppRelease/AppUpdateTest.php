<?php

use App\Models\AppRelease;
use App\Models\AttendanceTablet;
use App\Models\Branch;
use App\Services\AppRelease\AppReleasePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

const UPD_EMPLOYEE_PKG = 'com.cahayaoptimakarya.hris_mobile';
const UPD_TABLET_PKG = 'com.cahayaoptimakarya.hris_tablet';

test('aplikasi hp: tanpa versi wajib hanya ditawari update', function () {
    $publisher = app(AppReleasePublisher::class);
    $publisher->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 50, '1.0.0'), null, null);
    $publisher->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 52, '1.0.0'), 'Perbaikan login', null);

    $this->getJson('/api/app-update/employee?version_code=45')
        ->assertOk()
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.update_required', false)
        ->assertJsonPath('data.min_version_code', 0)
        ->assertJsonPath('data.latest.version_code', 52)
        ->assertJsonPath('data.latest.notes', 'Perbaikan login')
        ->assertJsonPath('data.latest.download_url', route('app-download.apk'));

    $this->getJson('/api/app-update/employee?version_code=52')
        ->assertJsonPath('data.update_available', false)
        ->assertJsonPath('data.update_required', false);

    $this->getJson('/api/app-update/employee')->assertStatus(422);
});

test('aplikasi hp: di bawah versi wajib tertinggi wajib update', function () {
    $publisher = app(AppReleasePublisher::class);
    $publisher->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 50, '1.0.0'), null, null)->update(['is_mandatory' => true]);
    $publisher->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 52, '1.0.0'), null, null)->update(['is_mandatory' => true]);
    $publisher->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 53, '1.0.0'), null, null);

    $this->getJson('/api/app-update/employee?version_code=51')
        ->assertJsonPath('data.min_version_code', 52)
        ->assertJsonPath('data.update_required', true)
        ->assertJsonPath('data.latest.version_code', 53);

    // Sudah di versi wajib: versi 53 hanya ditawarkan.
    $this->getJson('/api/app-update/employee?version_code=52')
        ->assertJsonPath('data.update_required', false)
        ->assertJsonPath('data.update_available', true);

    // Versi wajib tablet tidak memengaruhi aplikasi HP.
    $publisher->publish('tablet', fakeApk(UPD_TABLET_PKG, 2099, '1.0.0'), null, null)->update(['is_mandatory' => true]);
    $this->getJson('/api/app-update/employee?version_code=53')->assertJsonPath('data.update_required', false);
});

test('tablet: cek dan unduh update hanya dengan token tablet', function () {
    $publisher = app(AppReleasePublisher::class);
    $publisher->publish('tablet', fakeApk(UPD_TABLET_PKG, 2017, '1.0.0'), null, null);
    $publisher->publish('tablet', fakeApk(UPD_TABLET_PKG, 2020, '1.0.0'), null, null)->update(['is_mandatory' => true]);

    $this->getJson('/api/tablet/v1/app-update?version_code=2017')->assertUnauthorized();
    $this->getJson('/api/tablet/v1/app-update/apk')->assertUnauthorized();

    $branch = Branch::query()->create(['code' => 'KP'.Str::random(3), 'name' => 'Kantor Pusat', 'type' => 'office', 'is_active' => true]);
    $tablet = AttendanceTablet::query()->create(['name' => 'TAB-KP-01', 'branch_id' => $branch->id, 'status' => 'pending']);
    $code = $tablet->issuePairingCode();
    $token = $this->postJson('/api/tablet/v1/pair', ['code' => $code, 'device_model' => 'Samsung Tab A9'])->assertOk()->json('data.token');

    $this->withToken($token)->getJson('/api/tablet/v1/app-update?version_code=2017')
        ->assertOk()
        ->assertJsonPath('data.update_required', true)
        ->assertJsonPath('data.latest.version_code', 2020)
        ->assertJsonPath('data.latest.download_url', route('api.tablet.app-update.apk'));

    $this->withToken($token)->get('/api/tablet/v1/app-update/apk')
        ->assertOk()
        ->assertDownload('hris-tablet-absensi-1.0.0.apk');
});

test('superadmin menyalakan dan mematikan wajib update dari web', function () {
    $release = app(AppReleasePublisher::class)->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 52, '1.0.0'), null, null);
    $admin = superadmin();

    $this->actingAs($admin)->get(route('android-apps.index'))
        ->assertOk()
        ->assertSee('Belum ada versi wajib');

    $this->actingAs($admin)->patch(route('android-apps.mandatory', $release))->assertRedirect();
    expect($release->fresh()->is_mandatory)->toBeTrue()
        ->and(AppRelease::minimumVersionCode('employee'))->toBe(52);

    $this->actingAs($admin)->get(route('android-apps.index'))->assertSee('tidak bisa dipakai sampai diupdate');

    $this->actingAs($admin)->patch(route('android-apps.mandatory', $release));
    expect($release->fresh()->is_mandatory)->toBeFalse();
});

test('hanya superadmin yang bisa mengubah wajib update', function () {
    $release = app(AppReleasePublisher::class)->publish('employee', fakeApk(UPD_EMPLOYEE_PKG, 52, '1.0.0'), null, null);
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->patch(route('android-apps.mandatory', $release))->assertForbidden();
    expect($release->fresh()->is_mandatory)->toBeFalse();
});

test('unggah manual bisa langsung ditandai wajib', function () {
    $this->actingAs(superadmin())->post(route('android-apps.store'), [
        'app' => 'employee', 'apk' => fakeApk(UPD_EMPLOYEE_PKG, 60, '1.0.0'), 'is_mandatory' => '1',
    ])->assertRedirect();

    expect(AppRelease::latestFor('employee')->is_mandatory)->toBeTrue();
});
