<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccessControlRoleRequest;
use App\Http\Requests\BranchDepartmentRequest;
use App\Http\Requests\JobPositionAccessRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlController extends Controller
{
    public function index(): View
    {
        return view('access-control.index', [
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get()
                ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString()),
            'jobPositions' => JobPosition::query()
                ->with(['activeDepartments', 'defaultRole'])
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()
                ->with(['departments' => fn ($query) => $query->orderBy('name')])
                ->where('is_active', true)
                ->orderBy('city')
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateRole(AccessControlRoleRequest $request, Role $role): RedirectResponse
    {
        $permissions = $request->validated('permissions', []);

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('access-control.index')
            ->with('status', "Permission role {$role->name} berhasil diperbarui.");
    }

    public function updateJobPosition(JobPositionAccessRequest $request, JobPosition $jobPosition): RedirectResponse
    {
        $jobPosition->update($request->validated());

        return redirect()
            ->route('access-control.index')
            ->with('status', "Role default jabatan {$jobPosition->name} berhasil diperbarui.");
    }

    public function updateBranchDepartments(BranchDepartmentRequest $request, Branch $branch): RedirectResponse
    {
        $primaryDepartmentId = $request->integer('primary_department_id');
        $departments = collect($request->validated('departments', []))
            ->mapWithKeys(fn (int|string $departmentId): array => [
                (int) $departmentId => [
                    'is_primary' => (int) $departmentId === $primaryDepartmentId,
                    'is_active' => true,
                ],
            ])
            ->all();

        $branch->departments()->sync($departments);

        return redirect()
            ->route('access-control.index')
            ->with('status', "Divisi untuk lokasi {$branch->name} berhasil diperbarui.");
    }
}
