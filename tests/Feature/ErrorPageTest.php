<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('halaman galat memakai logo perusahaan dan bahasa Indonesia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('kabar.announcements.index'))
        ->assertForbidden()
        ->assertSee('img/company-logo.png', false)
        ->assertSee('Akses ditolak');

    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan');
});
