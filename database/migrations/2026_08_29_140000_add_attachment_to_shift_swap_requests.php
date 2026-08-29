<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti gambar untuk pengajuan tukar jadwal — mis. tangkapan layar percakapan
 * kesepakatan dengan rekan, atau surat pendukungnya.
 *
 * Kolomnya nullable meski lampirannya kini WAJIB diisi: pengajuan yang terlanjur
 * dibuat sebelum aturan ini ada tidak punya berkas, dan memaksa kolomnya NOT NULL
 * akan menggagalkan migrasi di server yang sudah berjalan. Kewajibannya ditegakkan
 * pada validasi formulir, bukan pada bentuk tabelnya.
 *
 * Berkasnya disimpan di disk privat, sama seperti lampiran cuti: isinya percakapan
 * pribadi antar karyawan, tidak boleh bisa dibuka siapa pun yang menebak URL-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('reason');
            // Nama asli disimpan supaya berkas yang diunduh peninjau tetap bernama
            // seperti saat diunggah, bukan nama acak hasil penyimpanan.
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
