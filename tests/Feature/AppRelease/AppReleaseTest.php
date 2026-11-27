<?php

use App\Models\AppRelease;
use App\Models\User;
use App\Services\AppRelease\AppReleasePublisher;
use App\Support\ApkInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** APK palsu (ZIP + AndroidManifest.xml biner) dengan paket dan versi tertentu. */
function fakeApk(string $package, int $versionCode, string $versionName): UploadedFile
{
    $strings = ['versionCode', 'versionName', 'package', 'manifest', $package, $versionName];

    $offsets = '';
    $body = '';
    foreach ($strings as $s) {
        $offsets .= pack('V', strlen($body));
        $body .= chr(strlen($s)).chr(strlen($s)).$s."\0";
    }
    while (strlen($body) % 4) {
        $body .= "\0";
    }
    $poolHeader = 28;
    $pool = pack('vvVVVVVV', 0x0001, $poolHeader, $poolHeader + strlen($offsets) + strlen($body), count($strings), 0, 0x100, $poolHeader + strlen($offsets), 0).$offsets.$body;

    $resMap = pack('vvV', 0x0180, 8, 16).pack('VV', 0x0101021B, 0x0101021C);

    $attr = fn (int $name, int $raw, int $type, int $data) => pack('VVVvCCV', 0xFFFFFFFF, $name, $raw, 8, 0, $type, $data);
    $attrs = $attr(0, 0xFFFFFFFF, 0x10, $versionCode)
        .$attr(1, 5, 0x03, 5)
        .$attr(2, 4, 0x03, 4);
    $element = pack('VVvvvvvv', 0xFFFFFFFF, 3, 20, 20, 3, 0, 0, 0).$attrs;
    $start = pack('vvVVV', 0x0102, 16, 16 + strlen($element), 1, 0xFFFFFFFF).$element;

    $inner = $pool.$resMap.$start;
    $manifest = pack('vvV', 0x0003, 8, 8 + strlen($inner)).$inner;

    $path = tempnam(sys_get_temp_dir(), 'apk');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('AndroidManifest.xml', $manifest);
    $zip->addFromString('classes.dex', str_repeat('x', 1024));
    $zip->close();

    return new UploadedFile($path, 'app-release.apk', null, null, true);
}

function superadmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    return $user;
}

const EMPLOYEE_PKG = 'com.cahayaoptimakarya.hris_mobile';
const TABLET_PKG = 'com.cahayaoptimakarya.hris_tablet';

test('apk info reads package and version', function () {
    $info = ApkInfo::read(fakeApk(EMPLOYEE_PKG, 42, '1.0.0')->getRealPath());

    expect($info->package)->toBe(EMPLOYEE_PKG)
        ->and($info->versionCode)->toBe(42)
        ->and($info->versionName)->toBe('1.0.0');
});

test('superadmin uploads both apps and downloads the tablet apk', function () {
    $admin = superadmin();

    $this->actingAs($admin)->post(route('android-apps.store'), ['app' => 'employee', 'apk' => fakeApk(EMPLOYEE_PKG, 3, '1.0.0'), 'notes' => 'Rilis awal'])
        ->assertRedirect(route('android-apps.index'));
    $this->actingAs($admin)->post(route('android-apps.store'), ['app' => 'tablet', 'apk' => fakeApk(TABLET_PKG, 7, '1.0.0')])
        ->assertRedirect(route('android-apps.index'));

    $tablet = AppRelease::latestFor('tablet');
    expect($tablet->version_code)->toBe(7)
        ->and($tablet->uploaded_by)->toBe($admin->id);
    Storage::disk('local')->assertExists($tablet->file_path);

    $this->actingAs($admin)->get(route('android-apps.index'))
        ->assertOk()
        ->assertSee('Aplikasi tablet absensi wajah')
        ->assertSee('Rilis awal');

    $this->actingAs($admin)->get(route('android-apps.download', $tablet))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.android.package-archive')
        ->assertDownload('hris-tablet-absensi-1.0.0.apk');
});

test('apk of the other app or a non-apk file is rejected', function () {
    $admin = superadmin();

    $this->actingAs($admin)->post(route('android-apps.store'), ['app' => 'employee', 'apk' => fakeApk(TABLET_PKG, 7, '1.0.0')])
        ->assertSessionHasErrors('apk');
    $this->actingAs($admin)->post(route('android-apps.store'), ['app' => 'tablet', 'apk' => UploadedFile::fake()->createWithContent('x.apk', 'bukan zip')])
        ->assertSessionHasErrors('apk');

    expect(AppRelease::count())->toBe(0);
});

test('only superadmin can open the page or download the tablet apk', function () {
    $release = app(AppReleasePublisher::class)->publish('tablet', fakeApk(TABLET_PKG, 7, '1.0.0'), null, null);

    $hr = User::factory()->create();
    $hr->forceFill(['bypass_team_scope' => true])->save();

    $this->actingAs($hr)->get(route('android-apps.index'))->assertForbidden();
    $this->actingAs($hr)->get(route('android-apps.download', $release))->assertForbidden();
    $this->actingAs($hr)->post(route('android-apps.store'), ['app' => 'tablet', 'apk' => fakeApk(TABLET_PKG, 8, '1.0.1')])->assertForbidden();
    $this->actingAs($hr)->delete(route('android-apps.destroy', $release))->assertForbidden();

    auth()->logout();
    $this->get(route('android-apps.download', $release))->assertRedirect(route('login'));
});

test('public page serves only the latest employee app, never the tablet app', function () {
    $publisher = app(AppReleasePublisher::class);

    $this->get(route('app-download.page'))->assertOk()->assertSee('Aplikasi belum tersedia');
    $this->get(route('app-download.apk'))->assertNotFound();

    $publisher->publish('tablet', fakeApk(TABLET_PKG, 99, '9.9.9'), null, null);
    $this->get(route('app-download.apk'))->assertNotFound();

    $publisher->publish('employee', fakeApk(EMPLOYEE_PKG, 5, '1.0.2'), 'Perbaikan login', null);
    $publisher->publish('employee', fakeApk(EMPLOYEE_PKG, 4, '1.0.1'), null, null);

    $this->get(route('app-download.page'))->assertOk()->assertSee('Versi 1.0.2')->assertSee('Perbaikan login')->assertDontSee('9.9.9');
    $this->get(route('app-download.apk'))->assertOk()->assertDownload('hris-karyawan-1.0.2.apk');

    $this->get(route('login'))->assertSee(route('app-download.page'));
});

test('superadmin deletes a release and its file', function () {
    $admin = superadmin();
    $release = app(AppReleasePublisher::class)->publish('employee', fakeApk(EMPLOYEE_PKG, 5, '1.0.2'), null, $admin->id);

    $this->actingAs($admin)->delete(route('android-apps.destroy', $release))->assertRedirect(route('android-apps.index'));

    expect(AppRelease::count())->toBe(0);
    Storage::disk('local')->assertMissing($release->file_path);
});

test('github actions publishes with the token; endpoint is off without one', function () {
    $this->postJson('/api/app-releases', ['app' => 'tablet', 'apk' => fakeApk(TABLET_PKG, 7, '1.0.0')])->assertNotFound();

    config(['services.app_release.publish_token' => 'rahasia-ci']);

    $this->withToken('salah')->postJson('/api/app-releases', ['app' => 'tablet', 'apk' => fakeApk(TABLET_PKG, 7, '1.0.0')])->assertUnauthorized();

    $this->withToken('rahasia-ci')->postJson('/api/app-releases', ['app' => 'tablet', 'apk' => fakeApk(TABLET_PKG, 7, '1.0.0'), 'notes' => 'Tablet: suara'])
        ->assertCreated()
        ->assertJsonPath('data.version_code', 7);

    expect(AppRelease::latestFor('tablet')->source)->toBe('ci');
});

test('sidebar and tablet page show the link only to superadmin', function () {
    $this->actingAs(superadmin())->get(route('profile.edit'))->assertOk()->assertSee(route('android-apps.index'));

    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->assertDontSee(route('android-apps.index'));
});

test('upload via xhr returns json with redirect and flashes status', function () {
    $this->actingAs(superadmin())
        ->postJson(route('android-apps.store'), [
            'app' => AppRelease::TABLET,
            'apk' => fakeApk(TABLET_PKG, 2016, '1.0.0'),
        ])
        ->assertOk()
        ->assertJsonPath('redirect', route('android-apps.index'))
        ->assertSessionHas('status');

    expect(AppRelease::latestFor(AppRelease::TABLET)?->version_code)->toBe(2016);
});

test('upload via xhr with wrong apk returns 422 json', function () {
    $this->actingAs(superadmin())
        ->postJson(route('android-apps.store'), [
            'app' => AppRelease::TABLET,
            'apk' => fakeApk(EMPLOYEE_PKG, 5, '1.0.0'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('apk');
});

test('android apps page shows upload progress script and size limit', function () {
    $this->actingAs(superadmin())
        ->get(route('android-apps.index'))
        ->assertOk()
        ->assertSee('data-apk-upload', false)
        ->assertSee('app-arm64-v8a-release.apk');
});
