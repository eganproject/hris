<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menu "Pengumuman HR" dan "Moderasi Kabar" tadinya hanya diberikan ke role
 * hr-manager, sehingga superadmin dan role HR lain mendapat 403. Izin itu kini
 * juga diberikan ke role superadmin dan ke setiap role/pengguna yang memegang
 * attendance-daily.update (HR yang mengelola absensi). Hanya menambah baris.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete',
        'kabar-moderation.view', 'kabar-moderation.update',
    ];

    private const HR_SOURCE = 'attendance-daily.update';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $roleIds = DB::table('roles')
            ->where('guard_name', $guard)
            ->whereIn('name', User::SUPER_ADMIN_ROLES)
            ->pluck('id');

        $sourceId = DB::table('permissions')->where('name', self::HR_SOURCE)->where('guard_name', $guard)->value('id');
        $userRows = collect();

        if ($sourceId) {
            $roleIds = $roleIds->merge(DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id'));
            $userRows = DB::table('model_has_permissions')->where('permission_id', $sourceId)->get(['model_id', 'model_type']);
        }

        $roleIds = $roleIds->unique();

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now(),
                ]);

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }

            foreach ($userRows as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $id, 'model_id' => $row->model_id, 'model_type' => $row->model_type,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja kosong: baris yang ditambahkan tidak bisa dibedakan dari pemberian
        // lewat Kontrol Akses, jadi tidak dicabut otomatis.
    }
};
