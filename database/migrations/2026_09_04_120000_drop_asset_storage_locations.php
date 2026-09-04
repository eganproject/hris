<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membatalkan lokasi penyimpanan aset: modulnya diputuskan tidak jadi dipakai, dan
 * rencana aset kembali seperti semula tanpa keterangan tempat penyimpanan.
 *
 * Dibuat sebagai migration pembatal, bukan dengan menghapus ketiga migration
 * pembuatnya, karena ketiganya sudah terlanjur masuk ke main dan mungkin sudah
 * dijalankan. Menghapus berkasnya akan menyisakan tabel dan kolom yang menggantung
 * di basis data yang sudah terlanjur memakainya, tanpa satu pun jalan untuk
 * membersihkannya lewat artisan.
 *
 * Seluruh langkahnya berpenjaga keberadaan, jadi aman dijalankan di basis data yang
 * belum pernah menerima ketiga migration itu.
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
        // Kolomnya lebih dulu: ia memegang foreign key ke tabel yang akan dibuang.
        if (Schema::hasColumn('assets', 'storage_location_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('storage_location_id');
            });
        }

        Schema::dropIfExists('asset_storage_locations');

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

    /**
     * Sengaja tidak membangun ulang apa pun. Membatalkan pembatalan berarti
     * menghidupkan kembali sebuah modul yang kodenya sudah tidak ada di aplikasi —
     * tabel kosong tanpa satu pun halaman yang membacanya. Bila modul ini suatu saat
     * dihidupkan lagi, ia datang bersama migration barunya sendiri.
     */
    public function down(): void
    {
        //
    }
};
