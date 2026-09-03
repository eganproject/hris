<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motto pribadi karyawan — kalimat singkat yang mereka tulis sendiri di halaman
 * Profil Saya, lalu tampil di kartu profil dan halaman detail karyawan. Nullable:
 * mengisinya tidak wajib, dan seluruh karyawan yang sudah ada tetap sah tanpa ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('motto', 160)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('motto');
        });
    }
};
