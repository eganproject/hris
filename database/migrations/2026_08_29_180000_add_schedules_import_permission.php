<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin "Impor" untuk menu Jadwal Kerja.
 *
 * Sebelumnya mengunggah roster dari Excel menumpang pada izin "Ubah", sehingga tidak
 * pernah muncul sebagai kolom tersendiri di matriks Kontrol Akses — admin tidak punya
 * cara memberikannya, dan yang belum memegang "Ubah" hanya menemui 403 tanpa
 * penjelasan.
 *
 * Izinnya langsung diberikan kepada setiap role dan pengguna yang SEKARANG memegang
 * schedules.update, supaya tidak ada satu pun orang yang kehilangan kemampuan yang
 * hari ini sudah ia punya. Pemisahannya baru terasa untuk pemberian hak berikutnya.
 */
return new class extends Migration
{
    private const SOURCE = 'schedules.update';

    private const TARGET = 'schedules.import';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $sourceId = DB::table('permissions')
            ->where('name', self::SOURCE)
            ->where('guard_name', $guard)
            ->value('id');

        $targetId = DB::table('permissions')
            ->where('name', self::TARGET)
            ->where('guard_name', $guard)
            ->value('id');

        if (! $targetId) {
            $targetId = DB::table('permissions')->insertGetId([
                'name' => self::TARGET,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($sourceId) {
            $this->copyTo($sourceId, $targetId);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::TARGET)
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Salin kepemilikan izin lama ke izin baru, baik lewat role maupun langsung. */
    private function copyTo(int $sourceId, int $targetId): void
    {
        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $sourceId)
            ->pluck('role_id')
            ->reject(fn ($roleId) => DB::table('role_has_permissions')
                ->where('permission_id', $targetId)
                ->where('role_id', $roleId)
                ->exists());

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $targetId,
                'role_id' => $roleId,
            ]);
        }

        $holders = DB::table('model_has_permissions')
            ->where('permission_id', $sourceId)
            ->get()
            ->reject(fn ($row) => DB::table('model_has_permissions')
                ->where('permission_id', $targetId)
                ->where('model_id', $row->model_id)
                ->where('model_type', $row->model_type)
                ->exists());

        foreach ($holders as $row) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => $targetId,
                'model_id' => $row->model_id,
                'model_type' => $row->model_type,
            ]);
        }
    }
};
