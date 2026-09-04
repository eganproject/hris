<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin serah-terima aset dan menu "Aset Saya".
 *
 * Aturannya sama dengan migration izin aset sebelumnya: hanya menambah baris.
 *
 * Bedanya satu — my-assets.view diberikan kepada SETIAP role, bukan hanya
 * superadmin. Menu itu hanya memperlihatkan aset milik penggunanya sendiri, dan
 * karyawan wajib mengonfirmasi serah-terima: menutupnya di balik pemberian hak satu
 * per satu berarti aset yang sudah diserahkan menggantung tanpa pengakuan, hanya
 * karena tidak ada yang ingat memberi centang izinnya.
 *
 * Wewenang menyerahkan, menerima, dan memindahkan aset tetap ditahan untuk
 * superadmin saja; sisanya diputuskan sadar lewat Kontrol Akses.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ADMIN_PERMISSIONS = [
        'asset-assignments.view',
        'asset-assignments.assign',
        'asset-assignments.return',
        'asset-assignments.transfer',
    ];

    private const SELF_SERVICE_PERMISSION = 'my-assets.view';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $superAdminIds = DB::table('roles')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach (self::ADMIN_PERMISSIONS as $name) {
            $this->grant($this->permissionId($name, $guard), $superAdminIds);
        }

        // Setiap role yang ada hari ini, termasuk role karyawan biasa.
        $allRoleIds = DB::table('roles')->where('guard_name', $guard)->pluck('id');

        $this->grant($this->permissionId(self::SELF_SERVICE_PERMISSION, $guard), $allRoleIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', [...self::ADMIN_PERMISSIONS, self::SELF_SERVICE_PERMISSION])
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(string $name, string $guard): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id');

        return (int) ($id ?? DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /** @param  \Illuminate\Support\Collection<int, int>  $roleIds */
    private function grant(int $permissionId, $roleIds): void
    {
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
};
