<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Absen aplikasi yang bertanda tidak lagi menunggu persetujuan HR (keputusan Ega
 * 2026-11-25): tandanya tetap disimpan di kolom flags, antrean "menunggu" dikosongkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mobile_attendance_logs')->where('review_status', 'pending')->update(['review_status' => null]);
    }

    public function down(): void
    {
        // Tidak dikembalikan: antrean persetujuan sudah tidak dipakai.
    }
};
