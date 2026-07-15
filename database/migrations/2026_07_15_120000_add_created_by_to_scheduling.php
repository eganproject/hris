<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lacak pembuat pola jadwal & penugasan pola, agar tiap pengguna hanya melihat
 * data yang dibuatnya sendiri (pemegang attendance.view.all tetap melihat semua).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('end_date')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_patterns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
