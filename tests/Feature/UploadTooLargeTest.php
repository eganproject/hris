<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Unggahan yang melampaui post_max_size ditolak middleware GLOBAL ValidatePostSize,
 * yang berjalan sebelum sesi dimulai. Artinya pesan "maksimal 2 MB" milik form tidak
 * pernah muncul, dan pesan pengganti pun tidak bisa dititipkan ke flash session —
 * satu-satunya tempat menaruh keterangan adalah halaman galatnya sendiri.
 */
test('the 413 page explains the limit instead of showing a bare error', function () {
    // Persis yang dilemparkan ValidatePostSize saat body permintaan kebesaran.
    Route::get('/__too-large', fn () => throw new PostTooLargeException);

    $this->get('/__too-large')
        ->assertStatus(413)
        ->assertSee('Unggahan Anda melebihi batas server')
        // Batas tiap jenis berkas disebut, supaya pengguna tahu harus sekecil apa.
        ->assertSee('2 MB', false)
        ->assertSee('10 MB', false);
});

test('the page stands alone, so it renders for a visitor with no session', function () {
    Route::get('/__too-large', fn () => throw new PostTooLargeException);

    // Tata letak aplikasi memerlukan sesi & pengguna login; keduanya belum ada saat
    // ValidatePostSize melempar. Halaman ini harus utuh tanpa sidebar dan tanpa
    // dialihkan ke /login — kalau tidak, galatnya berganti jadi galat lain.
    $this->get('/__too-large')
        ->assertStatus(413)
        ->assertDontSee('data-sidebar-group', false)
        ->assertSee('Kembali');
});

test('the server limits are raised above what the app itself validates', function () {
    // Kalau batas server lebih rendah daripada aturan validasi, yang menjawab adalah
    // 413 dan pesan validasinya tidak pernah terlihat. Impor Excel = 10 MB.
    $userIni = file_get_contents(base_path('public/.user.ini'));

    expect($userIni)->toContain('upload_max_filesize = 16M')
        ->and($userIni)->toContain('post_max_size = 20M');

    // Apache dengan mod_php mengabaikan .user.ini, jadi .htaccess membawa nilai sama.
    $htaccess = file_get_contents(base_path('public/.htaccess'));

    expect($htaccess)->toContain('php_value upload_max_filesize 16M')
        ->and($htaccess)->toContain('php_value post_max_size 20M');
});
