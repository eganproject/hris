<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Struktur organisasi dari data karyawan: pohon pelaporan berdasarkan manager_id.
 * Selalu dibatasi DataScope, jadi seorang atasan hanya melihat cabang pohon yang
 * memang boleh ia lihat. Akar pohon adalah karyawan tanpa atasan, atau yang
 * atasannya berada di luar cakupan pengguna.
 */
class OrgChartController extends Controller
{
    public function __invoke(Request $request): View
    {
        $scope = DataScope::forEmployees($request->user());
        $branchId = $request->integer('branch_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;

        $employees = $scope->employees()
            ->active()
            ->byBranch($branchId)
            ->byDepartment($departmentId)
            ->with(['jobPosition', 'branch', 'departments'])
            ->orderBy('full_name')
            ->get();

        $byId = $employees->keyBy('id');
        $childrenMap = $employees->groupBy('manager_id');

        // Guard against bad data (a manager cycle) so the recursion always terminates.
        $visited = [];

        $build = function (Employee $employee) use (&$build, $childrenMap, &$visited): array {
            $visited[$employee->id] = true;

            $children = ($childrenMap[$employee->id] ?? collect())
                ->reject(fn (Employee $child) => isset($visited[$child->id]))
                ->map(fn (Employee $child) => $build($child))
                ->values()
                ->all();

            return ['employee' => $employee, 'children' => $children];
        };

        // Roots: no manager, or a manager the user cannot see (outside their scope).
        $roots = $employees
            ->filter(fn (Employee $employee) => ! $employee->manager_id || ! $byId->has($employee->manager_id))
            ->sortBy('full_name')
            ->values();

        $tree = $roots->map(fn (Employee $employee) => $build($employee))->all();

        return view('organization.chart', [
            'tree' => $tree,
            'branches' => $scope->branches(),
            'departments' => $scope->departments(),
            'branchId' => $branchId,
            'departmentId' => $departmentId,
            'hasNoScope' => $scope->isEmpty(),
            'summary' => [
                'shown' => $employees->count(),
                'managers' => $this->managerCount($employees, $childrenMap),
                'roots' => $roots->count(),
            ],
        ]);
    }

    /**
     * How many of the shown employees actually have at least one visible subordinate.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int|string|null, Collection<int, Employee>>  $childrenMap
     */
    private function managerCount(Collection $employees, Collection $childrenMap): int
    {
        return $employees
            ->filter(fn (Employee $employee) => ($childrenMap[$employee->id] ?? collect())->isNotEmpty())
            ->count();
    }
}
