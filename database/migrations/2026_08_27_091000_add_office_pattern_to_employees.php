<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola jam kantor per karyawan. Kosong = ikut pola default global (Pengaturan),
 * yang merupakan keadaan semua karyawan lama — jadi perilaku sebelum migration ini
 * tidak berubah sama sekali tanpa perlu backfill.
 *
 * nullOnDelete: bila polanya dihapus, karyawan otomatis jatuh kembali ke default
 * global alih-alih menunjuk pola yang sudah tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('office_pattern_id')
                ->nullable()
                ->after('follows_office_hours')
                ->constrained('schedule_patterns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_pattern_id');
        });
    }
};
