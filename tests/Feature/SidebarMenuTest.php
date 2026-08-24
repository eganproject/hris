<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('the sidebar renders collapsible groups without losing any menu item', function () {
    // See every menu regardless of permissions so the full sidebar renders.
    Gate::before(fn () => true);

    // Grup Self-service hanya muncul untuk akun yang tertaut ke data karyawan —
    // seluruh halamannya menjawab 403 tanpa itu. Jadi akun uji "melihat segalanya"
    // ini pun perlu punya data karyawan agar sidebar penuh benar-benar terender.
    $user = User::factory()->create();
    Employee::query()->create(['user_id' => $user->id, 'full_name' => 'Uji Sidebar', 'employment_status' => 'active']);

    $response = $this->actingAs($user)->get('/dashboard')->assertOk();

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
