<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: Employee} */
function profileOwner(): array
{
    $user = User::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $user->id, 'full_name' => 'Budi Santoso', 'employment_status' => 'active',
    ]);

    return [$user, $employee];
}

test('an employee can upload their own profile photo', function () {
    Storage::fake('public');
    [$user, $employee] = profileOwner();

    $this->actingAs($user)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('saya.jpg', 600, 600),
    ])->assertRedirect()->assertSessionHas('status', 'photo-updated');

    $path = $employee->fresh()->photo_path;

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('employees/photos/');
    Storage::disk('public')->assertExists($path);
});

test('replacing the photo removes the previous file', function () {
    Storage::fake('public');
    [$user, $employee] = profileOwner();

    $this->actingAs($user)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('lama.jpg', 600, 600),
    ])->assertRedirect();

    $old = $employee->fresh()->photo_path;

    $this->actingAs($user)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('baru.jpg', 600, 600),
    ])->assertRedirect();

    $new = $employee->fresh()->photo_path;

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);
});

test('the photo can be removed entirely', function () {
    Storage::fake('public');
    [$user, $employee] = profileOwner();

    $this->actingAs($user)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('saya.jpg', 600, 600),
    ])->assertRedirect();

    $path = $employee->fresh()->photo_path;

    $this->actingAs($user)->delete(route('profile.photo.destroy'))
        ->assertRedirect()
        ->assertSessionHas('status', 'photo-removed');

    expect($employee->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the same photo rules apply as on the HR form', function () {
    Storage::fake('public');
    [$user, $employee] = profileOwner();

    $reject = fn (UploadedFile $file) => $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => $file])
        ->assertSessionHasErrors('photo', null, 'updatePhoto');

    $reject(UploadedFile::fake()->image('kecil.jpg', 100, 100));            // di bawah 300x300
    $reject(UploadedFile::fake()->image('raksasa.jpg', 4000, 4000));        // di atas 3000x3000
    $reject(UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'));

    expect($employee->fresh()->photo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an account with no employee record cannot touch the photo', function () {
    Storage::fake('public');

    $orphan = User::factory()->create(); // tanpa baris karyawan

    $this->actingAs($orphan)->post(route('profile.photo.update'), [
        'photo' => UploadedFile::fake()->image('saya.jpg', 600, 600),
    ])->assertForbidden();

    $this->actingAs($orphan)->delete(route('profile.photo.destroy'))->assertForbidden();
});

test('the profile page offers the upload form and the current photo', function () {
    Storage::fake('public');
    [$user, $employee] = profileOwner();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Foto Profil')
        ->assertSee('data-image-input', escape: false)
        ->assertSee('enctype="multipart/form-data"', escape: false)
        // Belum ada foto: tombol hapus tidak ditawarkan.
        ->assertDontSee('Hapus Foto');

    $employee->update(['photo_path' => 'employees/photos/ada.jpg']);

    // fresh(): actingAs memakai instance User yang sama, dan relasi employee-nya masih
    // ter-cache dari permintaan di atas. Di HTTP sungguhan tiap permintaan memuat ulang.
    $this->actingAs($user->fresh())->get(route('profile.edit'))->assertOk()->assertSee('Hapus Foto');
});
