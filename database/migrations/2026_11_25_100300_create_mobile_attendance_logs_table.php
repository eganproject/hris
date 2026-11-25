<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setiap absen dari aplikasi HP, diterima maupun ditolak, beserta buktinya.
 * Jam yang diterima tetap ditulis ke tabel `attendances` yang sama dengan mesin;
 * tabel ini adalah jejak bukti + antrean tinjauan HR.
 *
 * client_uuid dibuat aplikasi saat tombol ditekan, sehingga antrean offline yang
 * dikirim ulang berkali-kali tetap tercatat satu kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 3);            // in | out
            $table->string('mode', 20);           // office | wfh | business_trip
            $table->string('result', 10);         // accepted | rejected
            $table->string('reject_code', 40)->nullable();
            $table->string('reject_reason')->nullable();
            $table->dateTime('captured_at');      // jam absen (jam HP saat tombol ditekan)
            $table->dateTime('received_at');      // jam server menerima
            $table->date('work_date')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->boolean('within_radius')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->string('wifi_bssid', 40)->nullable();
            $table->boolean('wifi_matched')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('blink_verified')->default(false);
            $table->boolean('is_mock_location')->default(false);
            $table->boolean('is_rooted')->default(false);
            $table->json('flags')->nullable();
            $table->string('employee_note')->nullable(); // alasan dari karyawan, mis. absen di luar area
            $table->string('review_status', 10)->nullable(); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type', 'captured_at']);
            $table->index(['review_status', 'captured_at']);
            $table->index(['result', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_attendance_logs');
    }
};
