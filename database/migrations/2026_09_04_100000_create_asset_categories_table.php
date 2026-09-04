<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori aset: laptop, monitor, kendaraan, dan seterusnya.
 *
 * asset_prefix ikut membentuk kode aset (AST-LPT-HO-0012), jadi ia bagian dari
 * identitas dan bukan sekadar label — lihat App\Support\AssetNumber.
 *
 * Kategori tidak pernah dihapus selama masih dipakai (lihat AssetCategoryController);
 * yang sudah tidak dipakai lagi cukup dinonaktifkan agar aset lama tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('asset_prefix', 10);

            // Kategori yang serialnya wajib (laptop, telepon) dibedakan dari yang
            // tidak punya serial sama sekali (kursi, meja) — validasinya mengikuti
            // kolom ini, bukan daftar keras di dalam kode.
            $table->boolean('requires_serial')->default(false);
            $table->unsignedSmallInteger('useful_life_months')->nullable();

            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
