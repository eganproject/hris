<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin untuk master Lokasi Penyimpanan aset.
 *
 * Aturannya sama dengan migration izin aset sebelumnya: hanya menambah baris, dan
 * hanya diberikan kepada role superadmin. Role lain diatur di Kontrol Akses supaya
 * pengaturan yang sudah berjalan di produksi tidak tertimpa.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'asset-storage-locations.view',
        'asset-storage-locations.create',
        'asset-storage-locations.update',
        'asset-storage-locations.delete',
    ];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $now = now();

        $roleIds = DB::table('roles')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', $guard)
                ->value('id');

            $permissionId ??= DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($roleIds as $roleId) {
                $alreadyGranted = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $alreadyGranted) {
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
