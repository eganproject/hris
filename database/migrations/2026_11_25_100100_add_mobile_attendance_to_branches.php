<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan absen lewat aplikasi HP per Lokasi Kerja. Semuanya opsional:
 * lokasi yang belum diaktifkan tetap memakai cara lama (mesin), sehingga
 * peluncurannya bisa bertahap lokasi demi lokasi, mulai dari Kantor Pusat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('mobile_attendance_enabled')->default(false)->after('is_active');
            $table->decimal('latitude', 10, 7)->nullable()->after('mobile_attendance_enabled');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius_m')->default(100)->after('longitude');
            // Mode ketat: absen di luar radius DITOLAK. Default mati = absen tetap
            // tercatat tetapi ditandai untuk ditinjau HR.
            $table->boolean('geofence_strict')->default(false)->after('geofence_radius_m');
            // BSSID (alamat MAC access point) Wi-Fi kantor, bukti tambahan lokasi.
            $table->json('wifi_bssids')->nullable()->after('geofence_strict');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['mobile_attendance_enabled', 'latitude', 'longitude', 'geofence_radius_m', 'geofence_strict', 'wifi_bssids']);
        });
    }
};
