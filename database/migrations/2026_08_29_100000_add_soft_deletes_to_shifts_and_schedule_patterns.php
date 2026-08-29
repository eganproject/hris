<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift dan pola jadwal dihapus secara arsip, bukan dibuang.
 *
 * Penghapusan permanen di sini merusak riwayat: shift yang dibuang membuat shift_id
 * pada absensi dan roster jadi null (nullOnDelete), sehingga rekap bulan-bulan lalu
 * kehilangan shift-nya; pola yang dibuang ikut menyeret SEMUA penugasannya
 * (cascadeOnDelete). Keduanya tidak bisa dibatalkan.
 *
 * Dengan deleted_at, barisnya tetap ada — hilang dari daftar dan pilihan, tapi tetap
 * terbaca oleh data yang menunjuknya — dan bisa dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
