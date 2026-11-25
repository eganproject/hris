<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HP yang terikat ke seorang karyawan. Satu karyawan hanya punya satu HP aktif;
 * HP pertama langsung aktif, pergantian HP menunggu persetujuan HR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 100); // dibuat aplikasi saat pertama dipasang, disimpan di secure storage
            $table->string('platform', 20);     // android | ios
            $table->string('model')->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('status', 20)->default('pending'); // active | pending | rejected | revoked
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            // Persetujuan karyawan (UU PDP) atas penyimpanan selfie & lokasi saat absen.
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'device_uuid']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
