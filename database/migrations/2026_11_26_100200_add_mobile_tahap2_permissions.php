<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin menu web "Pengumuman HR" dan "Moderasi Kabar", plus overtime.update
 * (HR mengambil alih keputusan lembur). Diberikan bawaan kepada role
 * hr-manager (bila ada); role lain diatur lewat Kontrol Akses. Hanya menambah baris.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete',
        'kabar-moderation.view', 'kabar-moderation.update',
        // HR mengambil alih keputusan lembur dari aplikasi HP.
        'overtime.update',
    ];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $roleId = DB::table('roles')->where('name', 'hr-manager')->where('guard_name', $guard)->value('id');

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now(),
                ]);

            if ($roleId && ! DB::table('role_has_permissions')->where('permission_id', $id)->where('role_id', $roleId)->exists()) {
                DB::table('role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
