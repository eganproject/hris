<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bukti absen mandiri (WFH / dinas luar): foto selfie plus koordinat saat
        // tombol ditekan. Murni rekaman — tidak ada radius/geofence yang menolak
        // absen, HR yang menilai dari foto dan titik petanya.
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('clock_in_photo_path')->nullable()->after('clock_out');
            $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_in_photo_path');
            $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
            $table->unsignedInteger('clock_in_accuracy_m')->nullable()->after('clock_in_longitude');

            $table->string('clock_out_photo_path')->nullable()->after('clock_in_accuracy_m');
            $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_out_photo_path');
            $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
            $table->unsignedInteger('clock_out_accuracy_m')->nullable()->after('clock_out_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_photo_path',
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_in_accuracy_m',
                'clock_out_photo_path',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_accuracy_m',
            ]);
        });
    }
};
