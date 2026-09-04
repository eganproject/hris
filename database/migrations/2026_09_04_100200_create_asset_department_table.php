<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebuah aset bisa dimiliki bersama oleh dua divisi (mis. kendaraan operasional yang
 * dipakai bergantian). Tabel ini menyimpan himpunan lengkapnya; assets.department_id
 * tetap ada sebagai divisi utama dan selalu menjadi salah satu anggota himpunan.
 *
 * Sama seperti department_employee — cakupan data membaca tabel ini, jadi aset milik
 * bersama tetap terlihat oleh kedua divisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'department_id']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_department');
    }
};
