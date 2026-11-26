<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablet absensi wajah di kantor (keputusan Ega 2026-11-26).
 *
 * - attendance_tablets: tablet Android yang didaftarkan HR di web, terikat ke satu
 *   Lokasi Kerja. Tablet masuk lewat kode pasangan sekali pakai, lalu memakai token
 *   Sanctum miliknya sendiri (bukan akun karyawan).
 * - employee_face_profiles: pola angka wajah (embedding) per karyawan untuk dicocokkan
 *   di dalam tablet, 1 foto depan untuk dilihat HR, persetujuan UU PDP, dan PIN
 *   cadangan (NIK + PIN). Foto pendaftaran lain tidak disimpan.
 * - tablet_attendance_logs: setiap absen dari tablet (diterima, diabaikan, ditolak,
 *   atau dibatalkan lewat tombol "Bukan saya") beserta foto bukti dan skornya.
 *   Jam yang diterima tetap ditulis ke tabel `attendances` yang sama dengan mesin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_tablets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('pending'); // pending | active | revoked
            $table->string('pairing_code_hash')->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('hr_pin_hash')->nullable();
            $table->string('device_model', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('queue_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('employee_face_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('embeddings')->nullable(); // JSON: daftar vektor float32 (base64)
            $table->string('embedding_model', 40)->nullable();
            $table->unsignedSmallInteger('embedding_dim')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->foreignId('enrolled_tablet_id')->nullable()->constrained('attendance_tablets')->nullOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tablet_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('attendance_tablet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 3)->nullable();   // in | out (ditentukan server)
            $table->string('method', 5);             // face | pin
            $table->string('result', 10);            // accepted | ignored | rejected | cancelled
            $table->string('reject_code', 40)->nullable();
            $table->string('reject_reason')->nullable();
            $table->dateTime('captured_at');
            $table->dateTime('received_at');
            $table->date('work_date')->nullable();
            $table->decimal('match_score', 6, 4)->nullable();
            $table->decimal('match_margin', 6, 4)->nullable();
            $table->boolean('liveness_checked')->default(false);
            $table->decimal('liveness_score', 6, 4)->nullable();
            $table->string('photo_path')->nullable();
            $table->json('flags')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'captured_at']);
            $table->index(['result', 'captured_at']);
            $table->index(['attendance_tablet_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablet_attendance_logs');
        Schema::dropIfExists('employee_face_profiles');
        Schema::dropIfExists('attendance_tablets');
    }
};
