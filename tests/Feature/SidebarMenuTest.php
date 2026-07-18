<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('the sidebar renders collapsible groups without losing any menu item', function () {
    // See every menu regardless of permissions so the full sidebar renders.
    Gate::before(fn () => true);

    $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

    // New collapsible group headers (Attendance split into three).
    foreach (['Absensi', 'Penjadwalan', 'Cuti &amp; Lembur', 'Karyawan', 'Organization', 'Self-service', 'System'] as $group) {
        $response->assertSee($group, false);
    }

    // Accordion wiring is present.
    $response->assertSee('data-sidebar-group', false)
        ->assertSee('data-sidebar-group-toggle', false);

    // Every menu item still exists (nothing dropped in the restructure).
    foreach ([
        'Dashboard', 'Data Karyawan', 'Kontrak', 'Bagan Organisasi',
        'Overview', 'Lokasi Kerja', 'Divisi', 'Jabatan',
        'Absensi Harian', 'Perangkat Absensi', 'Monitor Mesin', 'Koreksi Absensi',
        'Shift Kerja', 'Hari Libur', 'Pola Jadwal', 'Jadwal Kerja', 'Belum Terjadwal',
        'Cuti & Izin', 'Jenis & Kuota Cuti', 'Lembur', 'Tukar Jadwal',
        'Laporan', 'Pengaturan', 'Pengaturan Akses',
    ] as $item) {
        $response->assertSee($item, false);
    }
});
