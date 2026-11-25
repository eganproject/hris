<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin menu "Absen Aplikasi HP" (tinjauan absen + persetujuan ganti HP).
 *
 * Diberikan kepada setiap role/pengguna yang hari ini memegang izin papan absensi
 * harian yang setara, supaya HR langsung bisa memakainya tanpa mengatur ulang
 * Kontrol Akses. Hanya menambah baris; tidak ada hak lain yang diubah.
 */
return new class extends Migration
{
    /** @var array<string, string> izin baru => izin sumber */
    private const MAP = [
        'mobile-attendance.view' => 'attendance-daily.view',
        'mobile-attendance.update' => 'attendance-daily.update',
    ];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        foreach (self::MAP as $target => $source) {
            $targetId = DB::table('permissions')->where('name', $target)->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $target, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);

            $sourceId = DB::table('permissions')->where('name', $source)->where('guard_name', $guard)->value('id');

            if (! $sourceId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $targetId, 'role_id' => $roleId]);
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $sourceId)->get() as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'model_id' => $row->model_id,
                    'model_type' => $row->model_type,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::MAP))->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
