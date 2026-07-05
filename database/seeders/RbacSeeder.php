<?php

namespace Database\Seeders;

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
        $permissions = collect(config('rbac.permissions', []));

        $permissions->each(fn (string $permission) => Permission::findOrCreate($permission, $guard));

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
