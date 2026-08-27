<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai pola mana yang boleh dipakai sebagai "jam kantor". Dulu hanya ada satu
 * pola jam kantor untuk seluruh perusahaan (setting default_office_pattern_id),
 * sehingga kantor pusat dan cabang tidak bisa punya jam kerja berbeda.
 *
 * Tanda ini hanya mengatur apa yang boleh DITAWARKAN saat memilih pola di data
 * karyawan — bukan apa yang boleh dipakai saat jadwal diresolusi. Mencabut tanda
 * dari sebuah pola karena itu tidak pernah diam-diam mengubah jadwal karyawan yang
 * terlanjur memakainya.
 *
 * Pola yang sudah dipilih sebagai default global otomatis ikut ditandai, agar
 * pengaturan yang sudah berjalan tidak jadi tidak valid begitu migration ini naik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->boolean('is_office_pattern')->default(false)->after('is_active')->index();
        });

        $defaultId = DB::table('settings')->where('key', 'default_office_pattern_id')->value('value');

        if ($defaultId) {
            DB::table('schedule_patterns')->where('id', (int) $defaultId)->update(['is_office_pattern' => true]);
        }
    }

    public function down(): void
    {
        // Indexnya dibuang lebih dulu: SQLite menolak membuang kolom yang masih
        // ber-index, dan pemisahan ini tidak mengganggu MySQL.
        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->dropIndex(['is_office_pattern']);
        });

        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->dropColumn('is_office_pattern');
        });
    }
};
