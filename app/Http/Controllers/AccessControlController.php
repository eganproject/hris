<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccessControlRoleRequest;
use App\Http\Requests\BranchDepartmentRequest;
use App\Http\Requests\JobPositionAccessRequest;
use App\Http\Requests\UserScopeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\User;
use App\Support\MenuPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlController extends Controller
{
    public function index(Request $request): View
    {
        $userSearch = $request->string('user_search')->toString();
        $userRole = $request->string('user_role')->toString();

        return view('access-control.index', [
            'users' => User::query()
                ->with(['roles', 'accessBranches', 'accessDepartments'])
                ->when($userSearch, fn ($query, $search) => $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->when($userRole, fn ($query, $role) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'userFilters' => ['search' => $userSearch, 'role' => $userRole],
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
            // Baris = menu, kolom = aksi (config/rbac.php).
            'matrix' => MenuPermissions::matrix(),
            'actions' => config('rbac.actions'),
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

    /**
     * Role yang dikunci penuh: namanya dipakai di kode & seeder, jadi tidak boleh
     * diubah/dihapus/dikurangi haknya agar tidak ada yang mengunci diri sendiri.
     */
    private const PROTECTED_ROLES = ['superadmin', 'super-admin'];

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ], [], ['name' => 'nama role']);

        Role::findOrCreate($data['name'], 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access-control.index')->with('status', "Role \"{$data['name']}\" berhasil dibuat. Atur hak aksesnya di bawah.");
    }

    public function renameRole(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES, true), 403, 'Role sistem tidak dapat diganti namanya.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
        ], [], ['name' => 'nama role']);

        $role->update(['name' => $data['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access-control.index')->with('status', 'Nama role berhasil diperbarui.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES, true), 403, 'Role sistem tidak dapat dihapus.');

        // Jangan menghapus role yang masih dipakai — penggunanya akan kehilangan akses
        // secara diam-diam. Pindahkan mereka ke role lain lebih dulu.
        $users = $role->users()->count();

        if ($users > 0) {
            return redirect()->route('access-control.index')
                ->with('error', "Role \"{$role->name}\" masih dipakai {$users} pengguna. Pindahkan mereka ke role lain sebelum menghapusnya.");
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('access-control.index')->with('status', 'Role berhasil dihapus.');
    }

    public function updateRole(AccessControlRoleRequest $request, Role $role): RedirectResponse
    {
        // Superadmin harus tetap punya akses penuh — kalau tidak, tidak ada lagi yang
        // bisa memperbaiki hak akses yang terlanjur dicabut.
        abort_if(in_array($role->name, self::PROTECTED_ROLES, true), 403, 'Akses role sistem tidak dapat diubah.');

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

    /**
     * Set which work locations and divisions a user may see. Both lists may be empty
     * ("semua" on that axis) — but a user with neither, and without a "lihat semua"
     * permission, sees no data at all until one is set.
     */
    public function updateUserScope(UserScopeRequest $request, User $user): RedirectResponse
    {
        $user->accessBranches()->sync($request->validated('branches', []));
        $user->accessDepartments()->sync($request->validated('departments', []));
        $user->forceFill(['limit_to_subordinates' => $request->boolean('limit_to_subordinates')])->save();

        // Role menentukan menu & hak akses user. Disimpan bersama cakupan agar admin
        // bisa mengatur keduanya dari satu tempat.
        $user->syncRoles($request->validated('roles', []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('access-control.index')
            ->with('status', "Role & cakupan data {$user->name} berhasil diperbarui.");
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
