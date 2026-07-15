<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda cakupan tambahan: bila aktif, pengguna hanya melihat/menyesuaikan
 * karyawan di bawah garis atasannya (berjenjang, dari kolom manager_id) — bukan
 * seluruh lokasi/divisi. Per-pengguna, jadi tidak bergantung nama role tertentu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('limit_to_subordinates')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('limit_to_subordinates');
        });
    }
};
