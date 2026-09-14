<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin tersendiri untuk menu Jadwal Saya.
 *
 * Sebelumnya halaman /jadwal-saya tidak dijaga izin apa pun, sehingga tidak pernah
 * muncul sebagai baris di matriks Kontrol Akses: setiap akun yang tertaut data
 * karyawan melihatnya, dan itu tidak bisa disembunyikan per role.
 *
 * Izinnya langsung diberikan kepada setiap role dan pengguna yang SEKARANG memegang
 * my-attendance.view — menu self-service yang dimiliki semua karyawan — supaya
 * karyawan tidak kehilangan menu yang hari ini sudah mereka buka. Migration ini hanya
 * menambah baris — tidak ada pemberian hak yang sudah diatur lewat Kontrol Akses yang
 * ditimpa atau dihapus.
 */
return new class extends Migration
{
    private const SOURCE = 'my-attendance.view';

    private const TARGET = 'my-roster.view';

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
