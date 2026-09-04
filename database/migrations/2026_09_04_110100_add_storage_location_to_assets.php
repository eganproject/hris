<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Di mana barangnya disimpan ketika tidak sedang dipegang siapa pun.
 *
 * Nullable di basis data, tetapi diwajibkan oleh AssetRequest saat status aset
 * "Tersedia" — aset yang menganggur harus jelas ada di rak mana. Status lain
 * dibiarkan kosong karena barangnya memang tidak ada di gudang: Perawatan (di
 * vendor), Hilang (belum diketahui), Dipegang (ada pada karyawan).
 *
 * Kolomnya ditambahkan lewat migration tersendiri, bukan dengan menyunting
 * migration pembuat tabel assets — migration yang sudah pernah dijalankan tidak
 * pernah diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('storage_location_id')
                ->nullable()
                ->after('current_branch_id')
                ->constrained('asset_storage_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });
    }
};
