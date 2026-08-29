<?php

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(ShiftSwapRequest::ATTACHMENT_DISK));

/**
 * Dua hal terpisah yang dijaga di berkas ini:
 *
 * 1. Riwayat tukar jadwal. Dulu layar self-service hanya memuat pengajuan MILIK
 *    SENDIRI; begitu seseorang menjawab permintaan rekannya, permintaan itu lenyap
 *    dari layarnya dan tidak ada satu pun tempat untuk memeriksa apa yang pernah ia
 *    setujui.
 *
 * 2. Template impor jadwal yang berisi bawahan si pengunduh saja — tanpa mengubah
 *    pemeriksaan cakupan saat file-nya diunggah kembali.
 */
function settledSwap(Employee $requester, Employee $partner, string $status): ShiftSwapRequest
{
    return ShiftSwapRequest::query()->create([
        'requester_id' => $requester->id,
        'partner_id' => $partner->id,
        'requester_date' => now()->addDays(3)->toDateString(),
        'partner_date' => now()->addDays(3)->toDateString(),
        'type' => ShiftSwapRequest::TYPE_SWAP,
        'status' => $status,
        'decided_at' => now(),
    ]);
}

test('a request answered as the partner is kept in the history, not lost', function () {
    $pagi = Shift::query()->create(['code' => 'PG', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
    $siang = Shift::query()->create(['code' => 'SG', 'name' => 'Siang', 'start_time' => '15:00', 'end_time' => '23:00', 'is_active' => true]);

    [$requesterUser, $requester] = swapEmployee('Andi');
    [$partnerUser, $partner] = swapEmployee('Budi');

    $date = now()->addDays(3)->toDateString();
    scheduleRow($requester, $date, $pagi);
    scheduleRow($partner, $date, $siang);

    $this->actingAs($requesterUser)->post('/my-schedule/swaps', [
        'type' => 'swap',
        'partner_id' => $partner->id,
        'requester_date' => $date,
        'partner_date' => $date,
        'attachment' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertRedirect();

    $swap = ShiftSwapRequest::query()->latest('id')->firstOrFail();

    // Sebelum menjawab, permintaannya ada di tab "Berjalan" milik rekan.
    $this->actingAs($partnerUser)->get(route('my-schedule.index'))
        ->assertOk()
        ->assertSee('Diminta rekan')
        ->assertSee('Andi');

    $this->actingAs($partnerUser)
        ->patch(route('my-schedule.swaps.respond', $swap), ['decision' => 'reject'])
        ->assertRedirect();

    // Setelah dijawab ia keluar dari "Berjalan" —
    $this->actingAs($partnerUser)->get(route('my-schedule.index'))
        ->assertOk()
        ->assertSee('Tidak ada pengajuan yang sedang berjalan');

    // — tapi tetap bisa ditemukan di "Riwayat", lengkap dengan perannya.
    $this->actingAs($partnerUser)->get(route('my-schedule.index', ['tab' => 'riwayat']))
        ->assertOk()
        ->assertSee('Diminta rekan')
        ->assertSee('Ditolak')
        ->assertSee('Andi');
});

test('the history counts both roles and keeps them apart', function () {
    [$userA, $andi] = swapEmployee('Andi');
    [, $budi] = swapEmployee('Budi');

    settledSwap($andi, $budi, ShiftSwapRequest::STATUS_APPROVED);   // Andi mengajukan
    settledSwap($budi, $andi, ShiftSwapRequest::STATUS_REJECTED);   // Andi diminta
    settledSwap($budi, $andi, ShiftSwapRequest::STATUS_CANCELLED);

    $response = $this->actingAs($userA)->get(route('my-schedule.index', ['tab' => 'riwayat']));

    $response->assertOk()
        ->assertSee('Saya ajukan')
        ->assertSee('Diminta rekan')
        ->assertSee('Disetujui')
        ->assertSee('Ditolak')
        ->assertSee('Dibatalkan');
});

test('a swap between two other people never shows up in my history', function () {
    [$userA, $andi] = swapEmployee('Andi');
    [, $budi] = swapEmployee('Budi');
    [, $citra] = swapEmployee('Citra');

    settledSwap($budi, $citra, ShiftSwapRequest::STATUS_APPROVED);

    $this->actingAs($userA)->get(route('my-schedule.index', ['tab' => 'riwayat']))
        ->assertOk()
        ->assertSee('Belum ada riwayat tukar jadwal.');

    expect($andi->id)->not->toBeNull();
});

test('the roster import template holds only the downloader subordinates', function () {
    $hr = swapHr();

    // Atasan dengan dua bawahan langsung dan satu cucu-bawahan.
    $manager = Employee::query()->create(['user_id' => $hr->id, 'full_name' => 'Rina Atasan', 'employment_status' => 'active']);
    $anak1 = Employee::query()->create(['manager_id' => $manager->id, 'full_name' => 'Bawahan Satu', 'employment_status' => 'active']);
    $anak2 = Employee::query()->create(['manager_id' => $manager->id, 'full_name' => 'Bawahan Dua', 'employment_status' => 'active']);
    Employee::query()->create(['manager_id' => $anak1->id, 'full_name' => 'Cucu Bawahan', 'employment_status' => 'active']);
    Employee::query()->create(['full_name' => 'Orang Lain', 'employment_status' => 'active']);

    $names = templateNames($this, $hr);

    // Bawahan berjenjang ikut; yang di luar garis atasan tidak.
    expect($names)->toContain('Bawahan Satu')
        ->and($names)->toContain('Bawahan Dua')
        ->and($names)->toContain('Cucu Bawahan')
        ->and($names)->not->toContain('Orang Lain')
        // Atasannya sendiri bukan bawahannya sendiri.
        ->and($names)->not->toContain('Rina Atasan');

    expect($anak2->id)->not->toBeNull();
});

test('a downloader with no subordinates still gets the full template', function () {
    $hr = swapHr();

    Employee::query()->create(['full_name' => 'Karyawan Satu', 'employment_status' => 'active']);
    Employee::query()->create(['full_name' => 'Karyawan Dua', 'employment_status' => 'active']);

    // HR pusat dan superadmin tidak punya siapa pun di bawahnya; menyaring template
    // mereka jadi "bawahan saja" hanya akan memberi file kosong.
    $names = templateNames($this, $hr);

    expect($names)->toContain('Karyawan Satu')
        ->and($names)->toContain('Karyawan Dua');
});

/**
 * Nama-nama pada sheet "Jadwal" template yang diunduh. Berkas xlsx adalah arsip zip,
 * jadi isinya harus benar-benar dibuka — mencocokkan teks pada byte mentahnya akan
 * selalu gagal meski namanya memang ada di dalam.
 *
 * @return list<string>
 */
function templateNames($test, User $user): array
{
    $content = $test->actingAs($user)
        ->get(route('attendance.schedules.import.template', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $content);

    $rows = IOFactory::load($path)->getSheet(0)->toArray();
    @unlink($path);

    $cells = [];

    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if (is_string($cell) && $cell !== '') {
                $cells[] = $cell;
            }
        }
    }

    return $cells;
}
