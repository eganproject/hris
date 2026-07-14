<?php

namespace Database\Seeders;

use App\Support\MenuPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        // Permissions are derived from the menu catalog, so a new menu only has to be
        // declared once (config/rbac.php) to exist everywhere.
        collect(MenuPermissions::all())
            ->each(fn (string $permission) => Permission::findOrCreate($permission, $guard));

        foreach (config('rbac.roles', []) as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $permissionModels = Permission::query()
                ->where('guard_name', $guard)
                ->when(
                    $rolePermissions !== ['*'],
                    fn ($query) => $query->whereIn('name', $rolePermissions),
                )
                ->get();

            $role->syncPermissions($permissionModels);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
