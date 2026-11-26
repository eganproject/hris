<?php

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Post;
use App\Models\PostReport;
use App\Support\ApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/MobileHelpers.php';

uses(RefreshDatabase::class);

// Kabar dimatikan sejak 2026-11-26; tes ini menguji kodenya bila dibuka lagi.
beforeEach(fn () => config(['features.kabar' => true]));

test('pengumuman HR tampil untuk sasarannya dan bisa ditandai sudah dibaca', function () {
    $finance = Department::query()->create(['name' => 'Keuangan', 'code' => 'FIN']);
    $it = Department::query()->create(['name' => 'IT', 'code' => 'IT']);
    [$budi] = mobileEmployee('Budi', ['department_id' => $finance->id]);
    [$fajar] = mobileEmployee('Fajar', ['department_id' => $it->id]);

    $all = Announcement::query()->create(['title' => 'Libur akhir tahun', 'body' => 'Kantor tutup.', 'audience' => 'all', 'published_at' => now()->subHour()]);
    $forFinance = Announcement::query()->create(['title' => 'Tutup buku', 'body' => '...', 'audience' => 'department', 'department_id' => $finance->id, 'published_at' => now()->subHour()]);
    Announcement::query()->create(['title' => 'Terjadwal', 'body' => '...', 'audience' => 'all', 'published_at' => now()->addDay()]);

    mobileAs($fajar)->getJson('/api/mobile/v1/kabar')->assertOk()
        ->assertJsonCount(1, 'data.announcements')->assertJsonPath('data.unread_announcements', 1);
    mobileAs($fajar)->getJson("/api/mobile/v1/announcements/{$forFinance->id}")->assertNotFound();

    mobileAs($budi)->getJson('/api/mobile/v1/kabar')->assertOk()->assertJsonCount(2, 'data.announcements');
    mobileAs($budi)->postJson("/api/mobile/v1/announcements/{$all->id}/read")
        ->assertOk()->assertJsonPath('data.read_count', 1)->assertJsonPath('data.audience_count', 2);
    mobileAs($budi)->getJson("/api/mobile/v1/announcements/{$all->id}")->assertOk()->assertJsonPath('data.is_read', true);
});

test('postingan divisi hanya terlihat rekan sedivisi; suka, komentar, dan balasan', function () {
    Storage::fake('local');
    $finance = Department::query()->create(['name' => 'Keuangan', 'code' => 'FIN']);
    $it = Department::query()->create(['name' => 'IT', 'code' => 'IT']);
    [$ayu, $ayuEmp] = mobileEmployee('Ayu', ['department_id' => $finance->id]);
    [$rudi] = mobileEmployee('Rudi', ['department_id' => $finance->id]);
    [$fajar] = mobileEmployee('Fajar', ['department_id' => $it->id]);

    $postId = mobileAs($ayu)->post('/api/mobile/v1/posts', [
        'body' => 'Tutup buku selesai 🎉', 'audience' => 'division',
        'image' => UploadedFile::fake()->image('foto.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

    mobileAs($fajar)->getJson("/api/mobile/v1/posts/{$postId}")->assertNotFound();
    mobileAs($fajar)->getJson('/api/mobile/v1/kabar')->assertJsonCount(0, 'data.posts');

    mobileAs($rudi)->postJson("/api/mobile/v1/posts/{$postId}/like")->assertOk()->assertJsonPath('data.liked', true);
    $commentId = mobileAs($rudi)->postJson("/api/mobile/v1/posts/{$postId}/comments", ['body' => 'Mantap!'])->assertCreated()->json('data.id');
    $replyId = mobileAs($ayu)->postJson("/api/mobile/v1/posts/{$postId}/comments", ['body' => 'Makasih', 'parent_id' => $commentId])->assertCreated()->json('data.id');
    // Balasan untuk balasan tetap menempel di komentar teratas.
    mobileAs($rudi)->postJson("/api/mobile/v1/posts/{$postId}/comments", ['body' => '👍', 'parent_id' => $replyId])->assertCreated()
        ->assertJsonPath('data.parent_id', $commentId);

    mobileAs($ayu)->getJson("/api/mobile/v1/posts/{$postId}")->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.comments_count', 3)
        ->assertJsonCount(1, 'data.comments')
        ->assertJsonCount(2, 'data.comments.0.replies');

    mobileAs($rudi)->get("/api/mobile/v1/posts/{$postId}/image")->assertOk();
    expect($ayuEmp->user->notifications()->where('data->category', 'kabar')->count())->toBe(2);

    // Hanya penulis yang bisa menghapus.
    mobileAs($rudi)->deleteJson("/api/mobile/v1/posts/{$postId}")->assertForbidden();
    mobileAs($ayu)->deleteJson("/api/mobile/v1/posts/{$postId}")->assertOk();
});

test('postingan yang disembunyikan HR hilang dari feed kecuali bagi penulisnya', function () {
    [$ayu, $ayuEmp] = mobileEmployee('Ayu');
    [$rudi] = mobileEmployee('Rudi');
    $post = Post::query()->create(['employee_id' => $ayuEmp->id, 'body' => 'Halo', 'audience' => 'all']);

    mobileAs($rudi)->postJson("/api/mobile/v1/posts/{$post->id}/report", ['reason' => 'Spam'])->assertOk();
    expect(PostReport::query()->open()->count())->toBe(1);

    $post->update(['hidden_at' => now(), 'hidden_reason' => 'Tidak sesuai']);

    mobileAs($rudi)->getJson('/api/mobile/v1/kabar')->assertJsonCount(0, 'data.posts');
    mobileAs($ayu)->getJson('/api/mobile/v1/kabar')->assertJsonCount(1, 'data.posts')
        ->assertJsonPath('data.posts.0.is_hidden', true)
        ->assertJsonPath('data.posts.0.hidden_reason', 'Tidak sesuai');
});

test('struktur organisasi: ke atas hanya atasan langsung, ke bawah semua level', function () {
    [, $director] = mobileEmployee('Direktur');
    [, $manager] = mobileEmployee('Andi Manajer', ['manager_id' => $director->id]);
    [$meUser, $me] = mobileEmployee('Budi Supervisor', ['manager_id' => $manager->id]);
    mobileEmployee('Rekan Setingkat', ['manager_id' => $manager->id]);
    [, $ayu] = mobileEmployee('Ayu', ['manager_id' => $me->id]);
    mobileEmployee('Dimas', ['manager_id' => $ayu->id]);
    mobileEmployee('Rudi', ['manager_id' => $me->id]);

    $data = mobileAs($meUser)->getJson('/api/mobile/v1/org')->assertOk()->json('data');

    expect($data['manager']['name'])->toBe('Andi Manajer')
        ->and($data['subordinate_total'])->toBe(3)
        ->and(collect($data['subordinates'])->pluck('name')->all())->toBe(['Ayu', 'Rudi'])
        ->and($data['subordinates'][0]['children'][0]['name'])->toBe('Dimas')
        ->and(json_encode($data))->not->toContain('Direktur')
        ->and(json_encode($data))->not->toContain('Rekan Setingkat');
});

test('notifikasi dan profil dari HP', function () {
    [, $boss] = mobileEmployee('Andi Atasan');
    [$user, $me] = mobileEmployee('Budi', ['manager_id' => $boss->id, 'phone' => '0811']);

    app(ApprovalNotifier::class)->attendanceReminder($me, 'Andi Atasan');

    mobileAs($user)->getJson('/api/mobile/v1/notifications/unread-count')->assertJsonPath('data.count', 1);
    $id = mobileAs($user)->getJson('/api/mobile/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.category', 'attendance')->json('data.0.id');
    mobileAs($user)->postJson("/api/mobile/v1/notifications/{$id}/read")->assertOk();
    mobileAs($user)->getJson('/api/mobile/v1/notifications/unread-count')->assertJsonPath('data.count', 0);

    mobileAs($user)->getJson('/api/mobile/v1/profile')->assertOk()
        ->assertJsonPath('data.is_leader', false)->assertJsonPath('data.manager.name', 'Andi Atasan');
    mobileAs($boss->user)->getJson('/api/mobile/v1/profile')->assertJsonPath('data.is_leader', true)
        ->assertJsonPath('data.leader.team_size', 1);

    mobileAs($user)->patchJson('/api/mobile/v1/profile', ['phone' => '0899', 'address' => 'Jl. Mawar'])->assertOk();
    expect($me->fresh()->phone)->toBe('0899');
});
