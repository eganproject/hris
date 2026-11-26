<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Support\ApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/MobileHelpers.php';

uses(RefreshDatabase::class);

function heldAsset(Employee $employee, array $attributes = []): AssetAssignment
{
    $branch = Branch::query()->firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
    $category = AssetCategory::query()->firstOrCreate(['code' => 'LPT'], ['name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true]);
    $asset = Asset::query()->create([
        'category_id' => $category->id, 'name' => 'Laptop Lenovo',
        'owning_branch_id' => $branch->id, 'current_branch_id' => $branch->id,
        'status' => AssetStatus::Assigned->value, 'condition' => 'good',
    ]);

    return AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'employee_id' => $employee->id,
        'assigned_at' => now()->subDay(),
        'condition_out' => 'good',
        ...$attributes,
    ]);
}

test('aset saya menampilkan aset yang dipegang dan yang belum dikonfirmasi', function () {
    [$user, $me] = mobileEmployee('Rina');
    [, $other] = mobileEmployee('Budi');
    heldAsset($me);
    heldAsset($me, ['acknowledged_at' => now()]);
    heldAsset($me, ['returned_at' => now(), 'condition_in' => 'good']);
    heldAsset($other);

    mobileAs($user)->getJson('/api/mobile/v1/assets')
        ->assertOk()
        ->assertJsonCount(2, 'data.open')
        ->assertJsonCount(1, 'data.history')
        ->assertJsonPath('data.counts.unacknowledged', 1)
        ->assertJsonPath('data.open.0.asset.category', 'Laptop')
        ->assertJsonPath('data.history.0.state', 'returned');
});

test('pemegang bisa mengonfirmasi penerimaan aset dari HP, orang lain tidak', function () {
    [$user, $me] = mobileEmployee('Rina');
    [$otherUser] = mobileEmployee('Budi');
    $assignment = heldAsset($me);

    mobileAs($otherUser)->postJson("/api/mobile/v1/assets/{$assignment->id}/acknowledge")->assertForbidden();

    mobileAs($user)->postJson("/api/mobile/v1/assets/{$assignment->id}/acknowledge", ['note' => 'Lengkap dengan charger'])
        ->assertOk()
        ->assertJsonPath('data.state', 'held');

    expect($assignment->fresh()->acknowledged_at)->not->toBeNull()
        ->and($assignment->fresh()->acknowledgement_note)->toBe('Lengkap dengan charger');

    mobileAs($user)->postJson("/api/mobile/v1/assets/{$assignment->id}/acknowledge")->assertStatus(422);
});

test('notifikasi aset membawa id serah-terima untuk aplikasi HP', function () {
    [$user, $me] = mobileEmployee('Rina');
    $assignment = heldAsset($me);

    app(ApprovalNotifier::class)->assetAssigned($assignment);

    mobileAs($user)->getJson('/api/mobile/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.category', 'asset')
        ->assertJsonPath('data.0.meta.assignment_id', $assignment->id);
});

test('atasan bisa membuka profil dan riwayat absen anggota tim, bukan orang lain', function () {
    [$bossUser, $boss] = mobileEmployee('Hendra');
    [, $member] = mobileEmployee('Dewi', ['manager_id' => $boss->id, 'join_date' => '2022-03-01']);
    [, $outsider] = mobileEmployee('Okan');

    Attendance::query()->create([
        'employee_id' => $member->id, 'work_date' => now()->startOfMonth()->toDateString(),
        'status' => 'late', 'clock_in' => now()->startOfMonth()->setTime(8, 9), 'late_minutes' => 9,
    ]);

    mobileAs($bossUser)->getJson("/api/mobile/v1/team/members/{$member->id}")
        ->assertOk()
        ->assertJsonPath('data.person.name', 'Dewi')
        ->assertJsonPath('data.person.joined', '2022-03')
        ->assertJsonPath('data.person.is_direct', true)
        ->assertJsonPath('data.summary.late', 1)
        ->assertJsonCount(1, 'data.items');

    mobileAs($bossUser)->getJson("/api/mobile/v1/team/members/{$outsider->id}")->assertForbidden();
});

test('foto profil dikirim sebagai URL lengkap supaya bisa dimuat aplikasi', function () {
    [$user] = mobileEmployee('Rina', ['branch_id' => mobileBranch()]);
    [, $okan] = mobileEmployee('Okan', ['branch_id' => mobileBranch(), 'photo_path' => 'employees/photos/okan.jpg']);

    $url = mobileAs($user)->getJson("/api/mobile/v1/people/{$okan->id}")->assertOk()->json('data.photo_url');

    expect($url)->toStartWith('http')->toEndWith('/storage/employees/photos/okan.jpg');
});
