<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran pendukung pengajuan cuti/izin: surat keterangan sakit, surat tugas, atau
 * bukti lain. Berkasnya disimpan di disk privat (storage/app/private), bukan disk
 * publik seperti foto profil — surat sakit adalah data kesehatan dan tidak boleh
 * bisa dibuka siapa pun yang menebak URL-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
