<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\Employee;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;

/**
 * Struktur organisasi di aplikasi HP, dilihat dari posisi karyawan sendiri:
 * ke atas HANYA sampai atasan langsung, ke bawah seluruh pohon bawahannya
 * (semua level). Rekan setingkat dan atasan dari atasan tidak ditampilkan.
 */
class OrgController extends HrController
{
    public function show(): JsonResponse
    {
        $me = $this->employee()->loadMissing(['jobPosition', 'department', 'branch', 'manager.jobPosition', 'manager.department']);

        // Satu query untuk seluruh karyawan aktif, lalu pohonnya dirakit di memori
        // (sama seperti User::subordinateEmployeeIds) — kedalaman berapa pun tetap murah.
        $all = Employee::query()->active()
            ->with(['jobPosition', 'department'])
            ->get(['id', 'manager_id', 'full_name', 'job_position_id', 'department_id', 'photo_path']);
        $childrenOf = $all->groupBy('manager_id');
        $visited = [$me->id => true];

        $build = function (Employee $employee) use (&$build, $childrenOf, &$visited): array {
            $children = ($childrenOf[$employee->id] ?? collect())
                ->reject(fn (Employee $child) => isset($visited[$child->id]))
                ->sortBy('full_name')
                ->map(function (Employee $child) use (&$build, &$visited) {
                    $visited[$child->id] = true;

                    return $build($child);
                })
                ->values();

            return [
                ...Present::person($employee),
                'direct_count' => $children->count(),
                'total_count' => $children->sum(fn ($c) => 1 + $c['total_count']),
                'children' => $children->all(),
            ];
        };

        $tree = $build($me);

        return $this->ok([
            'department' => $me->department?->name,
            'branch' => $me->branch?->name,
            'manager' => Present::person($me->manager),
            'me' => collect($tree)->except('children')->all(),
            'subordinates' => $tree['children'],
            'subordinate_total' => $tree['total_count'],
        ]);
    }
}
