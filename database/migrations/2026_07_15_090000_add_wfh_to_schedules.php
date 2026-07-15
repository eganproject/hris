<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WFH sebagai bagian jadwal (bukan hanya pengajuan karyawan). Sebuah hari kerja
 * bisa ditandai WFH: shift-nya tetap menentukan jam kerja, is_wfh menentukan bahwa
 * hari itu dikerjakan dari rumah. Ada di pola (berulang) dan di jadwal harian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_pattern_days', function (Blueprint $table) {
            $table->boolean('is_wfh')->default(false)->after('shift_id');
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->boolean('is_wfh')->default(false)->after('is_day_off');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_pattern_days', function (Blueprint $table) {
            $table->dropColumn('is_wfh');
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropColumn('is_wfh');
        });
    }
};
