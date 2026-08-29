<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa yang boleh melihat SELURUH karyawan di Absensi Harian & Jadwal Kerja.
 *
 * Kedua halaman itu kini dipersempit ke bawahan pengguna. Pengecualiannya sengaja
 * dibuat sebagai saklar per pengguna di Kontrol Akses, bukan daftar nama role di
 * dalam kode: susunan role tiap perusahaan berbeda, dan menambah role baru tidak
 * boleh menuntut perubahan kode hanya untuk menentukan siapa yang melihat apa.
 *
 * Default-nya mati — pengguna baru dibatasi ke bawahannya. Tapi baris yang sudah ada
 * diisi menurut keadaan hari ini: siapa pun yang SEKARANG memegang attendance.view.all
 * langsung dinyalakan, supaya pemasangan pembaruan ini tidak tiba-tiba mengosongkan
 * layar orang yang selama ini bekerja normal. Sesudahnya admin tinggal mencabut yang
 * tidak perlu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bypass_team_scope')->default(false)->after('limit_to_subordinates');
        });

        $this->grandfatherCurrentHolders();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bypass_team_scope');
        });
    }

    /**
     * Nyalakan saklarnya bagi pemegang attendance.view.all saat ini — baik lewat role
     * maupun yang diberikan langsung ke penggunanya.
     */
    private function grandfatherCurrentHolders(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'attendance.view.all')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $viaRole = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('role_id');

        $userIds = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id')
            ->merge(
                $viaRole->isEmpty()
                    ? collect()
                    : DB::table('model_has_roles')
                        ->whereIn('role_id', $viaRole)
                        ->where('model_type', 'App\\Models\\User')
                        ->pluck('model_id')
            )
            ->unique();

        if ($userIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $userIds)->update(['bypass_team_scope' => true]);
        }
    }
};
