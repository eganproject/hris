<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin laporan Register Aset.
 *
 * Berdiri sendiri dari assets.view: daftar aset adalah layar operasional untuk
 * mengurus barang satu per satu, sedangkan register memuat rekap nilai perolehan
 * seluruh aset — angka yang biasanya hanya untuk manajemen dan keuangan. Keduanya
 * harus bisa diberikan terpisah.
 *
 * Hanya menambah baris, dan hanya diberikan ke role superadmin — sisanya diputuskan
 * di Kontrol Akses. Lihat 2026_09_04_160000_add_asset_import_permission.php.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = ['reports.assets.view', 'reports.assets.export'];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $roleIds = DB::table('roles')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', $guard)
                ->value('id');

            $permissionId ??= DB::table('permissions')->insertGetId([
                'name' => $permission,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
