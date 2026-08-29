<?php

use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(ShiftSwapRequest::ATTACHMENT_DISK));

/**
 * Bukti gambar pada pengajuan tukar jadwal.
 *
 * Pengajuan ini memindahkan tanggung jawab satu hari kerja ke orang lain, dan rekan
 * maupun HR memutuskannya tanpa bertemu si pengaju — jadi buktinya wajib, dan harus
 * bisa dilihat justru oleh dua pihak itu. Sebaliknya, berkasnya tersimpan di disk
 * privat: isinya percakapan pribadi antar karyawan.
 */
function swapWithProof(): array
{
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);

    [$user, $me] = swapEmployee('Andi');
    [$partnerUser, $partner] = swapEmployee('Budi');

    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $siang);

    test()->actingAs($user)->post('/my-schedule/swaps', [
        'type' => 'swap',
        'partner_id' => $partner->id,
        'requester_date' => $date,
        'partner_date' => $date,
        'attachment' => UploadedFile::fake()->image('kesepakatan.jpg', 800, 600),
    ])->assertRedirect(route('my-schedule.index'));

    return [$user, $partnerUser, ShiftSwapRequest::query()->latest('id')->firstOrFail()];
}

test('the image is required and the request is refused without it', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $pagi);

    $this->actingAs($user)
        ->from(route('my-schedule.index'))
        ->post('/my-schedule/swaps', [
            'type' => 'cover',
            'partner_id' => $partner->id,
            'requester_date' => $date,
        ])
        ->assertSessionHasErrors('attachment');

    expect(ShiftSwapRequest::query()->count())->toBe(0);
});

test('a file that is not an image is refused', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $pagi);

    // PDF diterima pada lampiran cuti, tapi di sini yang diminta memang gambar.
    $this->actingAs($user)
        ->from(route('my-schedule.index'))
        ->post('/my-schedule/swaps', [
            'type' => 'cover',
            'partner_id' => $partner->id,
            'requester_date' => $date,
            'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('attachment');

    expect(ShiftSwapRequest::query()->count())->toBe(0);
});

test('an oversized image is refused', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    [$user, $me] = swapEmployee('Andi');
    [, $partner] = swapEmployee('Budi');
    $date = now()->addDays(3)->toDateString();
    scheduleRow($me, $date, $pagi);
    scheduleRow($partner, $date, $pagi);

    $tooBig = UploadedFile::fake()->image('besar.jpg')->size((ShiftSwapRequest::ATTACHMENT_MAX_MB * 1024) + 1);

    $this->actingAs($user)
        ->from(route('my-schedule.index'))
        ->post('/my-schedule/swaps', [
            'type' => 'cover',
            'partner_id' => $partner->id,
            'requester_date' => $date,
            'attachment' => $tooBig,
        ])
        ->assertSessionHasErrors('attachment');

    expect(ShiftSwapRequest::query()->count())->toBe(0);
});

test('the stored image lands on the private disk with its original name', function () {
    [, , $swap] = swapWithProof();

    expect($swap->hasAttachment())->toBeTrue()
        ->and($swap->attachment_name)->toBe('kesepakatan.jpg')
        ->and($swap->attachment_mime)->toStartWith('image/')
        ->and($swap->attachment_size)->toBeGreaterThan(0);

    Storage::disk(ShiftSwapRequest::ATTACHMENT_DISK)->assertExists($swap->attachment_path);

    // Disk privat, bukan publik: berkasnya tidak boleh punya URL yang bisa ditebak.
    expect($swap->attachment_path)->toStartWith('swap-attachments/');
});

test('the requester, the partner and HR may open it — an outsider may not', function () {
    [$user, $partnerUser, $swap] = swapWithProof();

    $this->actingAs($user)->get(route('swaps.attachment', $swap))->assertOk();
    $this->actingAs($partnerUser)->get(route('swaps.attachment', $swap))->assertOk();
    $this->actingAs(swapHr())->get(route('swaps.attachment', $swap))->assertOk();

    // Karyawan lain yang tidak terlibat dan tidak punya swaps.view.
    [$outsider] = swapEmployee('Citra');
    $this->actingAs($outsider)->get(route('swaps.attachment', $swap))->assertForbidden();

    $this->actingAs(User::factory()->create())->get(route('swaps.attachment', $swap))->assertForbidden();
});

test('a missing file gives 404 instead of a server error', function () {
    [$user, , $swap] = swapWithProof();

    // Barisnya masih menyimpan path meski berkasnya hilang dari disk.
    Storage::disk(ShiftSwapRequest::ATTACHMENT_DISK)->delete($swap->attachment_path);

    $this->actingAs($user)->get(route('swaps.attachment', $swap))->assertNotFound();
});

test('the evidence link shows up for the partner and for HR', function () {
    [$user, $partnerUser, $swap] = swapWithProof();

    $link = route('swaps.attachment', $swap);

    // Pengaju melihatnya di daftar pengajuannya sendiri.
    $this->actingAs($user)->get(route('my-schedule.index'))->assertSee($link, false);

    // Rekan melihatnya di panel "menunggu persetujuan Anda" — pihak yang justru
    // paling butuh melihat buktinya sebelum memutuskan.
    $this->actingAs($partnerUser)->get(route('my-schedule.index'))->assertSee($link, false);

    // HR baru melihat pengajuannya setelah rekan menyetujui — dan justru di situ
    // buktinya paling dibutuhkan, karena HR memutuskan tanpa bertemu keduanya.
    $this->actingAs($partnerUser)
        ->patch(route('my-schedule.swaps.respond', $swap), ['decision' => 'accept'])
        ->assertRedirect();

    $this->actingAs(swapHr())->get(route('attendance.swaps.index'))->assertSee($link, false);
});
