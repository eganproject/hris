<?php

return [
    'permissions' => [
        'dashboard.view',
        'employees.view',
        // Lihat SEMUA lokasi & divisi (mengabaikan cakupan data user). Tanpa ini,
        // pemegang employees.view / attendance.view hanya melihat cakupannya sendiri.
        'employees.view.all',
        'employees.create',
        'employees.update',
        'employees.delete',
        'leave.request',
        'attendance.correction',
        'schedule.swap',
        'overtime.request',
        'attendance.view',
        'attendance.view.all',
        'attendance.create',
        'attendance.update',
        'attendance.delete',
        'organization.view',
        'organization.create',
        'organization.update',
        'organization.delete',
        'access-control.view',
        'access-control.update',
    ],

    'roles' => [
        'superadmin' => ['*'],
        'super-admin' => ['*'],
        // HR pusat: melihat seluruh lokasi & divisi. Untuk HR cabang, buat role
        // terpisah tanpa "*.view.all", lalu atur cakupannya di menu Kontrol Akses.
        'hr-manager' => [
            'dashboard.view',
            'employees.view',
            'employees.view.all',
            'employees.create',
            'employees.update',
            'employees.delete',
            'leave.request',
            'attendance.correction',
            'schedule.swap',
            'overtime.request',
            'attendance.view',
            'attendance.view.all',
            'attendance.create',
            'attendance.update',
            'attendance.delete',
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.delete',
        ],
        'employee-reader' => [
            'dashboard.view',
            'employees.view',
        ],
        'employee' => [
            'dashboard.view',
            'leave.request',
            'attendance.correction',
            'schedule.swap',
            'overtime.request',
        ],
    ],
];
