<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin untuk modul Aset (fase 1–2: master aset & kategori).
 *
 * Ditulis sebagai migration, bukan seeder: RbacSeeder menulis ulang seluruh daftar
 * izin tiap role dari config/rbac.php, sedangkan role di produksi sudah lama diatur
 * tangan lewat Kontrol Akses — menjalankan db:seed di sana akan menimpanya.
 *
 * Migration ini HANYA menambah baris. Izin barunya diberikan kepada role superadmin
 * saja, karena aplikasi ini tidak punya Gate::before untuk superadmin: haknya berupa
 * baris nyata di role_has_permissions, jadi tanpa langkah ini menu barunya justru
 * tidak terlihat oleh siapa pun. Role lain (HR, asset officer) sengaja dibiarkan
 * kosong — siapa yang boleh membuka menu Aset adalah keputusan sadar di Kontrol
 * Akses, bukan efek samping sebuah migration.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'assets.view',
        'assets.create',
        'assets.update',
        'assets.delete',
        'assets.export',
        'asset-categories.view',
        'asset-categories.create',
        'asset-categories.update',
        'asset-categories.delete',
        'assets.view.all',
    ];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $now = now();

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

            $this->grantToSuperAdmins((int) $permissionId, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $ids = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', $guard)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantToSuperAdmins(int $permissionId, string $guard): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');

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
};
