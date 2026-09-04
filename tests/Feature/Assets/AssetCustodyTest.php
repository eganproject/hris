<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function custodyOfficer(array $permissions = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = $permissions ?: [
        'assets.view', 'assets.view.all',
        'asset-assignments.view', 'asset-assignments.assign',
        'asset-assignments.return', 'asset-assignments.transfer',
    ];

    foreach ([...$permissions, 'assets.view.all', 'my-assets.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/** HR yang boleh memproses karyawan keluar. */
function custodyHr(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['employees.view', 'employees.update', 'employees.view.all'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['employees.view', 'employees.update', 'employees.view.all']);

    return $user;
}

/** @return array<string, mixed> */
function custodyFixture(): array
{
    $category = AssetCategory::query()->create([
        'code' => 'LAPTOP', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $other = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $department = Department::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);

    $employee = Employee::query()->create([
        'full_name' => 'Budi', 'employment_status' => 'active',
        'branch_id' => $branch->id, 'department_id' => $department->id,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id,
        'name' => 'Laptop Dell',
        'owning_branch_id' => $branch->id,
        'current_branch_id' => $branch->id,
        'department_id' => $department->id,
        'status' => AssetStatus::Available->value,
        'condition' => 'good',
    ])->refresh();

    return compact('category', 'branch', 'other', 'department', 'employee', 'asset');
}

test('penyerahan mencatat pemegang dan mengubah status jadi dipegang', function () {
    $f = custodyFixture();

    $this->actingAs(custodyOfficer())
        ->post(route('assets.assign', $f['asset']), [
            'employee_id' => $f['employee']->id,
            'condition_out' => 'good',
            'expected_return_at' => today()->addDays(30)->toDateString(),
            'purpose' => 'Kerja harian',
        ])
        ->assertRedirect(route('assets.show', $f['asset']));

    $asset = $f['asset']->fresh();
    $assignment = $asset->currentAssignment;

    expect($asset->status)->toBe(AssetStatus::Assigned)
        ->and($assignment)->not->toBeNull()
        ->and($assignment->employee_id)->toBe($f['employee']->id)
        ->and($assignment->acknowledged_at)->toBeNull()
        ->and($asset->transactions()->where('type', 'assigned')->count())->toBe(1);
});

test('aset yang sedang dipegang tidak bisa diserahkan lagi', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $lain = Employee::query()->create(['full_name' => 'Siti', 'employment_status' => 'active']);

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $this->actingAs($officer)
        ->post(route('assets.assign', $f['asset']), ['employee_id' => $lain->id, 'condition_out' => 'good'])
        ->assertSessionHas('error');

    expect(AssetAssignment::query()->where('asset_id', $f['asset']->id)->open()->count())->toBe(1);
});

test('karyawan nonaktif tidak bisa menerima aset', function () {
    $f = custodyFixture();
    $f['employee']->forceFill(['employment_status' => 'inactive'])->save();

    $this->actingAs(custodyOfficer())
        ->post(route('assets.assign', $f['asset']), ['employee_id' => $f['employee']->id, 'condition_out' => 'good'])
        ->assertSessionHasErrors('employee_id');
});

test('aset yang tidak layak tidak boleh diserahkan', function () {
    $f = custodyFixture();

    $this->actingAs(custodyOfficer())
        ->post(route('assets.assign', $f['asset']), ['employee_id' => $f['employee']->id, 'condition_out' => 'damaged'])
        ->assertSessionHasErrors('condition_out');
});

test('pengembalian menutup masa pegang dan status mengikuti hasil pemeriksaan', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    // Barangnya pulang dalam keadaan rusak: masuk antrean perawatan, bukan langsung
    // dinyatakan tersedia untuk orang berikutnya.
    $this->actingAs($officer)
        ->post(route('assets.return', $f['asset']), ['condition_in' => 'damaged'])
        ->assertRedirect(route('assets.show', $f['asset']));

    $asset = $f['asset']->fresh();
    $assignment = AssetAssignment::query()->where('asset_id', $asset->id)->firstOrFail();

    expect($asset->status)->toBe(AssetStatus::Maintenance)
        ->and($asset->currentAssignment)->toBeNull()
        ->and($assignment->returned_at)->not->toBeNull()
        ->and($assignment->condition_in?->value)->toBe('damaged')
        // Barisnya ditutup, bukan dihapus: riwayat pemegangnya tetap ada.
        ->and($assignment->employee_id)->toBe($f['employee']->id);
});

test('pengembalian barang yang baik mengembalikan aset ke tersedia', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);
    $this->actingAs($officer)->post(route('assets.return', $f['asset']), ['condition_in' => 'good']);

    expect($f['asset']->fresh()->status)->toBe(AssetStatus::Available);
});

test('menerima kembali aset yang tidak dipegang siapa pun ditolak', function () {
    $f = custodyFixture();

    $this->actingAs(custodyOfficer())
        ->post(route('assets.return', $f['asset']), ['condition_in' => 'good'])
        ->assertSessionHas('error');
});

test('hanya pemegangnya sendiri yang boleh mengonfirmasi penerimaan', function () {
    $f = custodyFixture();

    $holder = User::factory()->create();
    $f['employee']->forceFill(['user_id' => $holder->id])->save();
    Permission::findOrCreate('my-assets.view', 'web');
    $holder->givePermissionTo('my-assets.view');

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $assignment = AssetAssignment::query()->firstOrFail();

    // Orang lain — termasuk petugas yang menyerahkannya — tidak boleh mengakui.
    $orangLain = User::factory()->create();
    $orangLain->givePermissionTo('my-assets.view');
    $this->actingAs($orangLain)->post(route('my-assets.acknowledge', $assignment))->assertForbidden();

    $this->actingAs($holder)
        ->post(route('my-assets.acknowledge', $assignment), ['note' => 'Charger ikut.'])
        ->assertRedirect();

    $assignment->refresh();

    expect($assignment->acknowledged_at)->not->toBeNull()
        ->and($assignment->acknowledgement_note)->toBe('Charger ikut.')
        ->and($f['asset']->fresh()->transactions()->where('type', 'acknowledged')->count())->toBe(1);
});

test('halaman aset saya hanya memuat aset milik penggunanya', function () {
    $f = custodyFixture();

    $holder = User::factory()->create();
    $f['employee']->forceFill(['user_id' => $holder->id])->save();
    Permission::findOrCreate('my-assets.view', 'web');
    $holder->givePermissionTo('my-assets.view');

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $this->actingAs($holder)->get(route('my-assets.index'))
        ->assertOk()
        ->assertSee('Laptop Dell')
        ->assertSee('Belum dikonfirmasi');

    $orangLain = User::factory()->create();
    $orangLain->givePermissionTo('my-assets.view');

    $this->actingAs($orangLain)->get(route('my-assets.index'))->assertOk()->assertDontSee('Laptop Dell');
});

test('pemindahan lokasi tercatat dan tidak mengubah cabang pemilik', function () {
    $f = custodyFixture();
    $code = $f['asset']->asset_code;

    $this->actingAs(custodyOfficer())
        ->post(route('assets.transfer', $f['asset']), ['current_branch_id' => $f['other']->id])
        ->assertRedirect(route('assets.show', $f['asset']));

    $asset = $f['asset']->fresh();

    expect($asset->current_branch_id)->toBe($f['other']->id)
        // Cabang pemilik dan kode aset adalah identitas, bukan tempat barangnya.
        ->and($asset->owning_branch_id)->toBe($f['branch']->id)
        ->and($asset->asset_code)->toBe($code)
        ->and($asset->transactions()->where('type', 'transferred')->count())->toBe(1);
});

test('aset yang sedang dipegang tidak bisa dipindah lokasi', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $this->actingAs($officer)
        ->post(route('assets.transfer', $f['asset']), ['current_branch_id' => $f['other']->id])
        ->assertSessionHas('error');

    expect($f['asset']->fresh()->current_branch_id)->toBe($f['branch']->id);
});

test('proses keluar diblokir selama karyawan masih memegang aset', function () {
    $f = custodyFixture();

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $this->actingAs(custodyHr())
        ->patch(route('employees.resign', $f['employee']), [
            'exit_reason' => 'resigned',
            'exit_date' => today()->toDateString(),
        ])
        ->assertSessionHas('error');

    expect($f['employee']->fresh()->isInactive())->toBeFalse();
});

test('proses keluar lolos setelah asetnya dikembalikan', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);
    $this->actingAs($officer)->post(route('assets.return', $f['asset']), ['condition_in' => 'good']);

    $this->actingAs(custodyHr())
        ->patch(route('employees.resign', $f['employee']), [
            'exit_reason' => 'resigned',
            'exit_date' => today()->toDateString(),
        ])
        ->assertRedirect();

    expect($f['employee']->fresh()->isInactive())->toBeTrue();
});

test('atasan yang dibatasi ke bawahan melihat aset yang dipegang timnya', function () {
    $f = custodyFixture();

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $bossUser = custodyOfficer(['assets.view', 'asset-assignments.view']);
    $boss = Employee::query()->create([
        'full_name' => 'Atasan', 'employment_status' => 'active', 'user_id' => $bossUser->id,
        'branch_id' => $f['branch']->id,
    ]);
    $bossUser->forceFill(['limit_to_subordinates' => true])->save();

    // Sebelum garis atasannya ada, ia belum melihat apa pun.
    $this->actingAs($bossUser)->get(route('assets.index'))->assertOk()->assertDontSee('Laptop Dell');

    $f['employee']->forceFill(['manager_id' => $boss->id])->save();

    $this->actingAs($bossUser)->get(route('assets.index'))->assertOk()->assertSee('Laptop Dell');
    $this->actingAs($bossUser)->get(route('assets.show', $f['asset']))->assertOk();
});

test('menyerahkan, menerima, dan memindahkan adalah tiga izin terpisah', function () {
    $f = custodyFixture();

    // Cakupan asetnya sengaja dibuka penuh, supaya penolakan yang diuji di bawah
    // benar-benar datang dari izin aksinya — bukan dari cakupan data yang kosong.
    $hanyaLihat = custodyOfficer(['assets.view', 'assets.view.all', 'asset-assignments.view']);

    $this->actingAs($hanyaLihat)->get(route('assets.assignments.index'))->assertOk();
    $this->actingAs($hanyaLihat)->post(route('assets.assign', $f['asset']), [])->assertForbidden();
    $this->actingAs($hanyaLihat)->post(route('assets.return', $f['asset']), [])->assertForbidden();
    $this->actingAs($hanyaLihat)->post(route('assets.transfer', $f['asset']), [])->assertForbidden();

    // Yang boleh menyerahkan belum tentu boleh memindahkan.
    $penyerah = custodyOfficer(['assets.view', 'assets.view.all', 'asset-assignments.assign']);

    $this->actingAs($penyerah)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ])->assertRedirect(route('assets.show', $f['asset']));

    $this->actingAs($penyerah)->post(route('assets.transfer', $f['asset']), [])->assertForbidden();
});

test('pengingat konfirmasi dikirim sekali sehari setelah tiga hari didiamkan', function () {
    $f = custodyFixture();

    $holder = User::factory()->create();
    $f['employee']->forceFill(['user_id' => $holder->id])->save();

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $assignment = AssetAssignment::query()->firstOrFail();

    // Baru diserahkan hari ini — belum waktunya ditagih.
    $this->artisan('assets:notify-custody')->assertExitCode(0);
    expect($holder->fresh()->notifications()->count())->toBe(1); // hanya notifikasi penyerahan

    $assignment->forceFill(['assigned_at' => now()->subDays(4)])->save();

    $this->artisan('assets:notify-custody')->assertExitCode(0);
    expect($holder->fresh()->notifications()->count())->toBe(2)
        ->and($assignment->fresh()->acknowledgement_reminded_at)->not->toBeNull();

    // Dijalankan lagi di hari yang sama tidak menambah pengingat kedua.
    $this->artisan('assets:notify-custody')->assertExitCode(0);
    expect($holder->fresh()->notifications()->count())->toBe(2);
});

test('pengingat pengembalian mengikuti tenggatnya, dan yang telat ikut menagih petugas', function () {
    $f = custodyFixture();

    $holder = User::factory()->create();
    $f['employee']->forceFill(['user_id' => $holder->id])->save();

    $this->actingAs(custodyOfficer())->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id,
        'condition_out' => 'good',
        'expected_return_at' => today()->addDays(30)->toDateString(),
    ]);

    $assignment = AssetAssignment::query()->firstOrFail();
    $before = $holder->fresh()->notifications()->count();

    // 30 hari lagi bukan hari pengingat.
    $this->artisan('assets:notify-custody');
    expect($holder->fresh()->notifications()->count())->toBe($before);

    // Tinggal sehari lagi.
    $assignment->forceFill(['expected_return_at' => today()->addDay(), 'acknowledged_at' => now()])->save();
    $this->artisan('assets:notify-custody');
    expect($holder->fresh()->notifications()->count())->toBe($before + 1);

    // Sudah telat tujuh hari: karyawannya diingatkan lagi, petugas ikut ditagih.
    $officer = custodyOfficer();
    $assignment->forceFill([
        'expected_return_at' => today()->subDays(7),
        'return_reminded_at' => now()->subDays(2),
    ])->save();

    $this->artisan('assets:notify-custody');

    expect($holder->fresh()->notifications()->count())->toBe($before + 2)
        ->and($officer->fresh()->notifications()->count())->toBe(1);
});

test('halaman detail aset menampilkan panel serah-terima dan riwayatnya', function () {
    $f = custodyFixture();
    $officer = custodyOfficer();

    $this->actingAs($officer)->get(route('assets.show', $f['asset']))
        ->assertOk()
        ->assertSee('Pemegang Saat Ini')
        ->assertSee('Serahkan ke Karyawan')
        ->assertSee('Riwayat Perpindahan')
        ->assertSee('Aset ini sedang tidak dipegang siapa pun.');

    $this->actingAs($officer)->post(route('assets.assign', $f['asset']), [
        'employee_id' => $f['employee']->id, 'condition_out' => 'good',
    ]);

    $this->actingAs($officer)->get(route('assets.show', $f['asset']))
        ->assertOk()
        ->assertSee('Budi')
        ->assertSee('Menunggu konfirmasi karyawan')
        ->assertSee('Terima Kembali')
        ->assertSee('Diserahkan');
});
