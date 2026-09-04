<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat penyimpanan aset di dalam sebuah lokasi kerja, tersusun bertingkat:
 *
 *     Lantai 4 > Gudang A > Rak B
 *     Lantai 2 > Ruang Office A
 *
 * Kedalamannya tidak seragam — sebagian tempat memang cukup dua tingkat — jadi
 * yang disimpan adalah induknya (parent_id), bukan kolom lantai/gudang/rak yang
 * tetap. Kolom tetap akan memaksa "Lantai 2 > Ruang Office A" mengisi kolom rak
 * yang tidak ada isinya, dan menutup pintu untuk susunan yang lebih dalam nanti.
 *
 * full_path adalah nilai turunan ("Lantai 4 > Gudang A > Rak B") yang disimpan agar
 * daftar aset, penyaringan, dan ekspor tidak perlu menelusuri pohonnya baris per
 * baris. Ia ditulis ulang oleh model setiap kali nama atau induknya berubah —
 * pola yang sama dengan employees.employee_number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_storage_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            // restrictOnDelete, bukan cascade: menghapus satu gudang tidak boleh
            // diam-diam melenyapkan seluruh rak di dalamnya. Penghapusannya dijaga
            // controller, yang menolak selama masih ada anak atau aset di dalamnya.
            $table->foreignId('parent_id')->nullable()->constrained('asset_storage_locations')->restrictOnDelete();

            $table->string('code', 30)->nullable();
            $table->string('name', 120);
            $table->string('full_path', 500);
            $table->unsignedTinyInteger('depth')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'parent_id']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_storage_locations');
    }
};
