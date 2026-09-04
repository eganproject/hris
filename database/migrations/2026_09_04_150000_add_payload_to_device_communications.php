<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isi mentah tiap kiriman mesin absensi.
 *
 * Sebelumnya yang dicatat hanya jenis kiriman, jumlah record, dan IP-nya —
 * cukup untuk tahu mesinnya hidup, tapi tidak cukup untuk menjawab pertanyaan yang
 * justru sering muncul: "mesin benar-benar mengirim tap itu atau tidak?". Tanpa
 * isinya, satu-satunya bukti adalah punch yang berhasil masuk, sehingga kiriman yang
 * ditolak atau salah bentuk tidak meninggalkan jejak apa pun.
 *
 * payload_bytes menyimpan ukuran ASLI kiriman, sedangkan payload dipangkas bila
 * terlalu besar. Dua kolom, bukan satu, supaya pemangkasan tidak pernah menyamar
 * sebagai kiriman yang memang pendek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_communications', function (Blueprint $table) {
            $table->text('payload')->nullable()->after('records_count');
            $table->unsignedInteger('payload_bytes')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('device_communications', function (Blueprint $table) {
            $table->dropColumn(['payload', 'payload_bytes']);
        });
    }
};
