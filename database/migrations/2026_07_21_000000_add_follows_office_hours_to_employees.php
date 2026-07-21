<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karyawan "jam kantor" tidak perlu dijadwalkan: pola jadwal mereka sama tiap
 * minggu. Ditandai dengan follows_office_hours; absensi mereka diturunkan langsung
 * dari pola jam kantor default (Pengaturan) tanpa memateralisasi baris roster.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('follows_office_hours')->default(false)->after('employment_status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('follows_office_hours');
        });
    }
};
