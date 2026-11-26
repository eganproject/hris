<?php

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Kabar dimatikan sejak 2026-11-26; tes ini menguji kodenya bila dibuka lagi.
beforeEach(fn () => config(['features.kabar' => true]));

test('HR menerbitkan pengumuman dan semua karyawan sasaran diberi tahu', function () {
    $hr = scopedUser(['announcements.view', 'announcements.create']);
    $staff = User::factory()->create();
    Employee::query()->create(['user_id' => $staff->id, 'full_name' => 'Budi', 'employment_status' => 'active']);

    $this->actingAs($hr)->post('/kabar/pengumuman', [
        'title' => 'Libur akhir tahun', 'body' => 'Kantor tutup 24–26 Des.', 'audience' => 'all',
    ])->assertRedirect();

    $announcement = Announcement::query()->firstOrFail();
    expect($staff->notifications()->first()->data['meta']['announcement_id'])->toBe($announcement->id);

    $this->actingAs($hr)->get("/kabar/pengumuman/{$announcement->id}")
        ->assertOk()->assertSee('Belum membaca · 1')->assertSee('Budi');
});

test('menu pengumuman tertutup tanpa izin', function () {
    $user = scopedUser(['dashboard.view']);

    $this->actingAs($user)->get('/kabar/pengumuman')->assertForbidden();
    $this->actingAs($user)->get('/kabar/moderasi')->assertForbidden();
});

test('HR menyembunyikan postingan yang dilaporkan dan laporannya tertutup', function () {
    $hr = scopedUser(['kabar-moderation.view', 'kabar-moderation.update']);
    $author = User::factory()->create();
    $employee = Employee::query()->create(['user_id' => $author->id, 'full_name' => 'Ayu', 'employment_status' => 'active']);
    $post = Post::query()->create(['employee_id' => $employee->id, 'body' => 'Isi bermasalah', 'audience' => 'all']);
    PostReport::query()->create(['post_id' => $post->id, 'employee_id' => $employee->id, 'reason' => 'Spam']);

    $this->actingAs($hr)->get('/kabar/moderasi')->assertOk()->assertSee('Isi bermasalah');

    $this->actingAs($hr)->patch("/kabar/moderasi/{$post->id}/sembunyikan", ['reason' => 'Tidak sesuai etika'])->assertRedirect();

    expect($post->fresh()->hidden_at)->not->toBeNull()
        ->and(PostReport::query()->open()->count())->toBe(0)
        ->and($author->notifications()->first()->data['title'])->toBe('Postingan disembunyikan HR');

    $this->actingAs($hr)->get("/kabar/moderasi/{$post->id}")->assertOk()->assertSee('Tidak sesuai etika');
});
