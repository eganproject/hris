<?php

return [
    'permissions' => [
        'dashboard.view',
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'leave.request',
        'attendance.view',
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
        'hr-manager' => [
            'dashboard.view',
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'leave.request',
            'attendance.view',
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
        ],
    ],
];
