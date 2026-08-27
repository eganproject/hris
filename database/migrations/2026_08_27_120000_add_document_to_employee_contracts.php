<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas kontrak yang sudah ditandatangani, menempel pada baris kontraknya sendiri
 * (bukan pada karyawan), supaya tiap perpanjangan menyimpan dokumennya masing-masing
 * dan riwayatnya tetap bisa ditelusuri.
 *
 * Disimpan di disk privat (storage/app/private), sama seperti lampiran cuti: kontrak
 * memuat gaji dan data pribadi, jadi tidak boleh bisa dibuka siapa pun yang menebak
 * URL-nya. Satu-satunya jalan keluar adalah rute berotorisasi.
 *
 * Semua kolom nullable dan tanpa backfill: kontrak lama tetap valid tanpa dokumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
            // Nama asli disimpan supaya berkas yang diunduh tetap bernama seperti saat
            // diunggah, bukan nama acak hasil penyimpanan.
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime')->nullable()->after('document_name');
            $table->unsignedInteger('document_size')->nullable()->after('document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_mime', 'document_size']);
        });
    }
};
