<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pengguna dengan sekumpulan izin apa adanya — dipakai untuk menguji tiap layar
 * unggah berkas tanpa menumpang helper milik berkas test lain.
 *
 * @param  list<string>  $permissions
 */
function uploaderWith(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** Berkas yang jelas-jelas bukan Excel, untuk menguji penolakan import. */
function bukanExcel(): UploadedFile
{
    return UploadedFile::fake()->create('bukan-excel.pdf', 40, 'application/pdf');
}

test('a rejected employee import reopens its modal instead of failing silently', function () {
    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.import']);

    $response = $this->actingAs($user)
        ->from(route('employees.index'))
        ->post(route('employees.import'), ['file' => bukanExcel()]);

    $response->assertRedirect(route('employees.index'))
        ->assertSessionHasErrors('file');

    // Halaman tujuan harus MEMBUKA modal importnya; kalau tetap tersembunyi,
    // pesan kesalahan di dalamnya tidak pernah terlihat pengguna.
    $html = $this->actingAs($user)->get(route('employees.index'))->assertOk()->getContent();

    expect($html)->toContain('data-import-modal')
        ->and($html)->not->toContain('data-import-modal hidden');
});

test('a rejected schedule import reopens its modal instead of failing silently', function () {
    $user = uploaderWith(['schedules.view', 'schedules.import', 'attendance.view.all']);

    $this->actingAs($user)
        ->from(route('attendance.schedules.index'))
        ->post(route('attendance.schedules.import'), ['file' => bukanExcel()])
        ->assertRedirect(route('attendance.schedules.index'))
        ->assertSessionHasErrors('file');

    $html = $this->actingAs($user)->get(route('attendance.schedules.index'))->assertOk()->getContent();

    expect($html)->toContain('data-import-modal')
        ->and($html)->not->toContain('data-import-modal hidden');
});

test('import rejection is explained in Indonesian, naming the accepted formats', function () {
    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.import']);

    $errors = $this->actingAs($user)
        ->post(route('employees.import'), ['file' => bukanExcel()])
        ->assertSessionHasErrors('file')
        ->getSession()->get('errors')->getBag('default')->get('file');

    expect($errors[0])->toContain('Excel')
        ->and($errors[0])->not->toContain('must be a file of type');
});

test('an oversized import file is explained in Indonesian', function () {
    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.import']);

    $errors = $this->actingAs($user)
        ->post(route('employees.import'), ['file' => UploadedFile::fake()->create('besar.xlsx', 11 * 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
        ->assertSessionHasErrors('file')
        ->getSession()->get('errors')->getBag('default')->get('file');

    expect($errors[0])->toContain('MB')
        ->and($errors[0])->not->toContain('kilobytes');
});

test('employee photo rejections are explained in Indonesian', function () {
    Storage::fake('public');

    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.create']);

    $errors = $this->actingAs($user)
        ->post(route('employees.store'), ['full_name' => 'Foto Salah', 'photo' => bukanExcel()])
        ->assertSessionHasErrors('photo')
        ->getSession()->get('errors')->getBag('default')->get('photo');

    expect(implode(' ', $errors))->not->toContain('must be')
        ->and(implode(' ', $errors))->toContain('JPG');
});

test('a too-small employee photo says what resolution is required, in Indonesian', function () {
    Storage::fake('public');

    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.create']);

    $errors = $this->actingAs($user)
        ->post(route('employees.store'), ['full_name' => 'Foto Kecil', 'photo' => UploadedFile::fake()->image('kecil.jpg', 120, 120)])
        ->assertSessionHasErrors('photo')
        ->getSession()->get('errors')->getBag('default')->get('photo');

    expect(implode(' ', $errors))->toContain('300')
        ->and(implode(' ', $errors))->not->toContain('invalid image dimensions');
});

test('a selfie rejection is explained in Indonesian', function () {
    Storage::fake('local');

    $user = uploaderWith(['my-attendance.view']);
    Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Absen Mandiri', 'employment_status' => 'active',
    ]);

    $errors = $this->actingAs($user)
        ->post(route('my-attendance.check-in'), [
            'photo' => bukanExcel(), 'latitude' => -7.25, 'longitude' => 112.75,
        ])
        ->assertSessionHasErrors('photo', null, 'selfie')
        ->getSession()->get('errors')->getBag('selfie')->get('photo');

    expect(implode(' ', $errors))->not->toContain('must be')
        ->and(implode(' ', $errors))->toContain('JPG');
});

test('the employee import modal is gated by the same permission as its button', function () {
    // Hak import saja, TANPA hak tambah karyawan. Dulu modalnya ikut
    // "employees.create": tombolnya muncul, ditekan, dan tidak terjadi apa-apa.
    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.import']);

    $html = $this->actingAs($user)->get(route('employees.index'))->assertOk()->getContent();

    expect($html)->toContain('data-open-import')
        ->and($html)->toContain('data-import-modal');
});

test('the schedule import modal is gated by the same permission as its button', function () {
    // Hak import saja, TANPA hak ubah jadwal — dulu modalnya ikut "schedules.update".
    $user = uploaderWith(['schedules.view', 'schedules.import', 'attendance.view.all']);

    $html = $this->actingAs($user)->get(route('attendance.schedules.index'))->assertOk()->getContent();

    expect($html)->toContain('data-open-import')
        ->and($html)->toContain('data-import-modal');
});

test('every file input carries the data the client-side guard needs', function () {
    $user = uploaderWith(['employees.view', 'employees.view.all', 'employees.create', 'employees.update']);

    $html = $this->actingAs($user)->get(route('employees.create'))->assertOk()->getContent();

    // Penjaga sisi klien membaca batas ukuran dari atribut ini; tanpa itu berkas
    // kebesaran baru ketahuan setelah diunggah sampai selesai.
    expect(substr_count($html, 'data-file-guard'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('data-max-mb');
});
