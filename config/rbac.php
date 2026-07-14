<?php

/**
 * Akses dibangun dari satu katalog menu. Setiap baris di sini menjadi satu baris di
 * matriks "Role & Permission" (Kontrol Akses), satu set permission (<key>.<aksi>),
 * dan penjaga rute/menu di sidebar. Menambah menu baru cukup di file ini.
 */
return [
    /** Kolom matriks: aksi => label. */
    'actions' => [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'export' => 'Ekspor',
        'import' => 'Impor',
    ],

    /**
     * Menu, dikelompokkan seperti di sidebar. "permissions" hanya diisi bila nama
     * permission-nya tidak mengikuti pola <key>.<aksi> (mis. cakupan data).
     */
    'menus' => [
        'Karyawan' => [
            'employees' => [
                'label' => 'Data Karyawan',
                'actions' => ['view', 'create', 'update', 'delete', 'export', 'import'],
            ],
        ],

        'Absensi' => [
            'attendance-daily' => ['label' => 'Absensi Harian', 'actions' => ['view', 'update']],
            'punches' => ['label' => 'Log Punch', 'actions' => ['view', 'update']],
            'corrections' => ['label' => 'Koreksi Absensi', 'actions' => ['view', 'update']],
            'overtime' => ['label' => 'Lembur (rekap HR)', 'actions' => ['view']],
            'swaps' => ['label' => 'Tukar Jadwal (HR)', 'actions' => ['view', 'update']],
            'devices' => ['label' => 'Perangkat Absensi', 'actions' => ['view', 'create', 'update', 'delete']],
            'shifts' => ['label' => 'Shift Kerja', 'actions' => ['view', 'create', 'update', 'delete']],
            'holidays' => ['label' => 'Hari Libur', 'actions' => ['view', 'create', 'update', 'delete']],
        ],

        'Jadwal' => [
            'schedule-patterns' => ['label' => 'Pola Jadwal', 'actions' => ['view', 'create', 'update', 'delete']],
            'schedules' => ['label' => 'Jadwal Kerja', 'actions' => ['view', 'create', 'update', 'delete']],
        ],

        'Cuti' => [
            'leave' => ['label' => 'Cuti & Izin', 'actions' => ['view', 'create', 'update', 'delete']],
            'leave-types' => ['label' => 'Jenis Cuti', 'actions' => ['view', 'create', 'update', 'delete']],
            'leave-balances' => ['label' => 'Kuota Cuti', 'actions' => ['view', 'update']],
        ],

        'Laporan' => [
            'reports.attendance' => ['label' => 'Rekap Kehadiran', 'actions' => ['view', 'export']],
            'reports.log' => ['label' => 'Log Absensi', 'actions' => ['view', 'export']],
            'reports.leave' => ['label' => 'Rekap Cuti', 'actions' => ['view', 'export']],
        ],

        'Organisasi' => [
            'organization' => ['label' => 'Struktur Organisasi', 'actions' => ['view']],
            'branches' => ['label' => 'Lokasi Kerja', 'actions' => ['view', 'create', 'update', 'delete']],
            'departments' => ['label' => 'Divisi', 'actions' => ['view', 'create', 'update', 'delete']],
            'job-positions' => ['label' => 'Jabatan', 'actions' => ['view', 'create', 'update', 'delete']],
        ],

        'Sistem' => [
            'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
            'settings' => ['label' => 'Pengaturan', 'actions' => ['view', 'update']],
            'access-control' => ['label' => 'Kontrol Akses', 'actions' => ['view', 'update']],
        ],

        'Self-service' => [
            'my-leave' => ['label' => 'Cuti Saya', 'actions' => ['view']],
            'my-attendance' => ['label' => 'Absensi Saya (koreksi)', 'actions' => ['view']],
            'my-schedule' => ['label' => 'Tukar Jadwal Saya', 'actions' => ['view']],
            'my-overtime' => ['label' => 'Lembur Saya', 'actions' => ['view']],
        ],

        // Cakupan data: mencentang "Lihat" = boleh melihat SEMUA lokasi & divisi,
        // mengabaikan cakupan yang diatur per pengguna. Lihat App\Support\DataScope.
        'Cakupan Data' => [
            'employees-scope' => [
                'label' => 'Data karyawan: semua lokasi & divisi',
                'actions' => ['view'],
                'permissions' => ['view' => 'employees.view.all'],
            ],
            'attendance-scope' => [
                'label' => 'Absensi, jadwal, cuti & laporan: semua lokasi & divisi',
                'actions' => ['view'],
                'permissions' => ['view' => 'attendance.view.all'],
            ],
        ],
    ],

    /**
     * Isi awal tiap role. ['*'] = seluruh permission. Setelah sistem berjalan, role
     * diatur lewat menu Kontrol Akses — daftar ini hanya dipakai saat seeding.
     */
    'roles' => [
        'superadmin' => ['*'],
        'super-admin' => ['*'],

        // HR pusat: seluruh menu operasional + lihat semua lokasi/divisi, tanpa
        // Kontrol Akses (pengaturan hak akses tetap milik superadmin).
        'hr-manager' => [
            'dashboard.view',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.export', 'employees.import',
            'attendance-daily.view', 'attendance-daily.update',
            'punches.view', 'punches.update',
            'corrections.view', 'corrections.update',
            'overtime.view',
            'swaps.view', 'swaps.update',
            'devices.view', 'devices.create', 'devices.update', 'devices.delete',
            'shifts.view', 'shifts.create', 'shifts.update', 'shifts.delete',
            'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete',
            'schedule-patterns.view', 'schedule-patterns.create', 'schedule-patterns.update', 'schedule-patterns.delete',
            'schedules.view', 'schedules.create', 'schedules.update', 'schedules.delete',
            'leave.view', 'leave.create', 'leave.update', 'leave.delete',
            'leave-types.view', 'leave-types.create', 'leave-types.update', 'leave-types.delete',
            'leave-balances.view', 'leave-balances.update',
            'reports.attendance.view', 'reports.attendance.export',
            'reports.log.view', 'reports.log.export',
            'reports.leave.view', 'reports.leave.export',
            'organization.view',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'departments.view', 'departments.create', 'departments.update', 'departments.delete',
            'job-positions.view', 'job-positions.create', 'job-positions.update', 'job-positions.delete',
            'settings.view', 'settings.update',
            'my-leave.view', 'my-attendance.view', 'my-schedule.view', 'my-overtime.view',
            'employees.view.all', 'attendance.view.all',
        ],

        'employee-reader' => [
            'dashboard.view',
            'employees.view',
        ],

        'employee' => [
            'dashboard.view',
            'my-leave.view',
            'my-attendance.view',
            'my-schedule.view',
            'my-overtime.view',
        ],
    ],
];
