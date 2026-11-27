<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wajib update: superadmin menandai versi tertentu sebagai "Wajib". Aplikasi
 * (HP karyawan / tablet) dengan versionCode di bawah versi wajib tertinggi
 * terhalang layar update sampai memasang versi terbaru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->dropColumn('is_mandatory');
        });
    }
};
