<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin mengimpor master aset dari Excel.
 *
 * Berdiri sendiri dari assets.create, seperti halnya impor roster dipisahkan dari
 * mengubah satu sel jadwal: mengunggah seratus baris aset sekaligus berbeda bobotnya
 * dengan mendaftarkan satu barang lewat formulir, dan harus bisa diberikan atau
 * dicabut sendiri.
 *
 * Hanya menambah baris, dan hanya diberikan ke role superadmin — sisanya diputuskan
 * di Kontrol Akses.
 */
return new class extends Migration
{
    private const PERMISSION = 'assets.import';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', $guard)
            ->value('id');

        $permissionId ??= DB::table('permissions')->insertGetId([
            'name' => self::PERMISSION,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleIds = DB::table('roles')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
