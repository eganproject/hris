<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveWorkflow;
use App\Support\ApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Siapa yang dikabari harus sama dengan siapa yang bisa membuka tautannya.
 *
 * Penyaring penerima di ApprovalNotifier memakai DataScope yang sama dengan halaman
 * tujuannya, jadi berkas ini menguji pasangan itu — bukan sekadar "ada notifikasi
 * terkirim". Notifikasi yang berujung daftar kosong atau 403 sama tidak bergunanya
 * dengan notifikasi yang tidak pernah dikirim.
 *
 * Helper scopedUser() & inboxTitles() ada di tests/Pest.php.
 */
test('cuti tanpa atasan hanya dikabarkan ke HR yang halaman cutinya memang berisi orang itu', function () {
    $branch = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $type = LeaveType::query()->create([
        'code' => 'IZ', 'name' => 'Izin', 'attendance_status' => 'leave',
        'is_paid' => true, 'counts_against_balance' => false, 'is_active' => true,
    ]);

    // Halaman Cuti & Izin memakai DataScope::forTeam(): tanpa saklar "lihat semua
    // karyawan", isinya hanya bawahan sendiri.
    $hrPusat = scopedUser(['leave.view', 'leave.update', User::SCOPE_BYPASS_ATTENDANCE]);
    $hrPusat->forceFill(['bypass_team_scope' => true])->save();

    $hrTanpaTim = scopedUser(['leave.view', 'leave.update', User::SCOPE_BYPASS_ATTENDANCE]);

    // Karyawan tanpa atasan: pengajuannya langsung jatuh ke HR.
    $employee = Employee::query()->create([
        'full_name' => 'Budi Tanpa Atasan', 'employment_status' => 'active', 'branch_id' => $branch->id,
    ]);

    app(LeaveWorkflow::class)->submit($employee, [
        'leave_type_id' => $type->id,
        'start_date' => today()->addDay()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'reason' => 'Urusan keluarga.',
    ]);

    expect(inboxTitles($hrPusat))->toBe(['Pengajuan Izin menunggu HR'])
        // Dulu ia ikut dikabari, lalu menemukan daftar kosong dan tombol setuju 403.
        ->and(inboxTitles($hrTanpaTim))->toBe([]);
});

test('aset telat dikabarkan menurut lokasi asetnya, bukan lokasi karyawan yang memegangnya', function () {
    $gudang = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $cabang = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);
    $category = AssetCategory::query()->create([
        'code' => 'LPT', 'name' => 'Laptop', 'asset_prefix' => 'LPT', 'is_active' => true,
    ]);

    $petugasGudang = scopedUser(['asset-assignments.assign']);
    $petugasGudang->accessBranches()->sync([$gudang->id]);

    $petugasCabang = scopedUser(['asset-assignments.assign']);
    $petugasCabang->accessBranches()->sync([$cabang->id]);

    // Aset milik gudang, dipegang orang cabang: yang harus menagihnya tetap gudang.
    $employee = Employee::query()->create([
        'full_name' => 'Budi', 'employment_status' => 'active', 'branch_id' => $cabang->id,
    ]);

    $asset = Asset::query()->create([
        'category_id' => $category->id, 'name' => 'Laptop Dell',
        'owning_branch_id' => $gudang->id, 'current_branch_id' => $gudang->id,
        'status' => AssetStatus::Assigned->value, 'condition' => 'good',
    ]);

    $assignment = AssetAssignment::query()->create([
        'asset_id' => $asset->id,
        'employee_id' => $employee->id,
        'assigned_at' => today()->subDays(40),
        'expected_return_at' => today()->subDays(3),
        'condition_out' => 'good',
    ]);

    app(ApprovalNotifier::class)->assetReturnReminder($assignment, -3);

    expect(inboxTitles($petugasGudang))->toBe(['Aset telat dikembalikan'])
        ->and(inboxTitles($petugasCabang))->toBe([]);
});

test('mesin absensi offline hanya mengabari yang mengurus lokasi mesin itu', function () {
    $ho = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    $hrHo = scopedUser(['devices.view']);
    $hrHo->accessBranches()->sync([$ho->id]);

    $hrSby = scopedUser(['devices.view']);
    $hrSby->accessBranches()->sync([$sby->id]);

    // Yang melihat seluruh data absensi tetap dikabari, apa pun lokasinya.
    $hrPusat = scopedUser(['devices.view', User::SCOPE_BYPASS_ATTENDANCE]);

    $device = Device::query()->create([
        'serial_number' => 'SN-001', 'name' => 'Mesin Lobi', 'branch_id' => $ho->id,
        'is_active' => true, 'last_seen_at' => now()->subHour(),
    ]);

    app(ApprovalNotifier::class)->deviceOffline($device, 60);

    expect(inboxTitles($hrHo))->toBe(['Mesin absensi offline'])
        ->and(inboxTitles($hrPusat))->toBe(['Mesin absensi offline'])
        ->and(inboxTitles($hrSby))->toBe([]);
});

test('mesin tanpa lokasi tetap mengabari semua pemegang izinnya', function () {
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    $hrSby = scopedUser(['devices.view']);
    $hrSby->accessBranches()->sync([$sby->id]);

    $device = Device::query()->create([
        'serial_number' => 'SN-002', 'name' => 'Mesin Tanpa Lokasi',
        'is_active' => true, 'last_seen_at' => now()->subHour(),
    ]);

    app(ApprovalNotifier::class)->deviceOffline($device, 60);

    expect(inboxTitles($hrSby))->toBe(['Mesin absensi offline']);
});

test('koreksi absensi mengikuti halamannya: garis atasan, kecuali yang dikecualikan', function () {
    $ho = Branch::query()->create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
    $sby = Branch::query()->create(['code' => 'SBY', 'name' => 'Surabaya', 'is_active' => true]);

    // Halaman Koreksi kini memakai DataScope::forTeam(), jadi HR cabang yang tidak
    // dikecualikan tidak lagi dikabari — daftarnya pun tidak akan memuat orang ini.
    $hrHo = scopedUser(['corrections.view', 'corrections.update']);
    $hrHo->accessBranches()->sync([$ho->id]);
    $hrHo->forceFill(['bypass_team_scope' => true])->save();

    $hrSby = scopedUser(['corrections.view', 'corrections.update']);
    $hrSby->accessBranches()->sync([$sby->id]);
    $hrSby->forceFill(['bypass_team_scope' => true])->save();

    $hrTanpaTim = scopedUser(['corrections.view', 'corrections.update']);
    $hrTanpaTim->accessBranches()->sync([$ho->id]);

    $employee = Employee::query()->create([
        'full_name' => 'Budi', 'employment_status' => 'active', 'branch_id' => $ho->id,
    ]);

    $correction = AttendanceCorrection::query()->create([
        'employee_id' => $employee->id,
        'work_date' => today()->subDay(),
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen.',
        'status' => AttendanceCorrection::STATUS_PENDING,
    ]);

    app(ApprovalNotifier::class)->correctionSubmitted($correction);

    expect(inboxTitles($hrHo))->toBe(['Koreksi absensi baru'])
        ->and(inboxTitles($hrSby))->toBe([])
        ->and(inboxTitles($hrTanpaTim))->toBe([]);
});
