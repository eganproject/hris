<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APK aplikasi Android yang disimpan di server HRIS sendiri: aplikasi HP
 * karyawan (boleh diunduh siapa saja dari halaman /unduh-aplikasi) dan
 * aplikasi tablet absensi wajah (hanya superadmin yang bisa mengunduh).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('app', 20); // employee | tablet
            $table->string('package_name');
            $table->unsignedBigInteger('version_code');
            $table->string('version_name', 50);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('upload'); // upload | ci
            $table->timestamps();

            $table->index(['app', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
