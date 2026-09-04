<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master aset — identitas permanen sebuah barang.
 *
 * Dua kolom lokasi sengaja dipisah dan sering tertukar kalau tidak dijelaskan:
 * owning_branch_id adalah unit yang MEMILIKI aset (dan tidak ikut berubah saat
 * barangnya dipindah), current_branch_id adalah tempat barangnya BERADA sekarang.
 * Kode aset dibentuk dari cabang pemilik, jadi memindahkan barang tidak pernah
 * mengubah kodenya.
 *
 * status dan condition juga bukan hal yang sama: status menjawab "aset sedang
 * berada pada proses apa" (tersedia, dipegang, diservis), condition menjawab
 * "keadaan fisiknya bagaimana" (baik, rusak). Menggabungkannya membuat aset rusak
 * yang masih dipegang karyawan tidak punya cara untuk dicatat.
 *
 * softDeletes hanya untuk registrasi draft yang batal. Aset yang sudah punya
 * riwayat tidak dihapus, melainkan berakhir di status retired/disposed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Nullable karena kodenya memuat id dan baru bisa ditulis sesaat setelah
            // baris ini ada — persis seperti employee_number (lihat Asset::booted()).
            $table->string('asset_code', 50)->nullable()->unique();

            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();

            $table->string('name');
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            // Unik bila terisi: MySQL dan SQLite sama-sama mengizinkan NULL berulang
            // pada unique index, jadi aset tanpa serial tidak saling bentrok.
            $table->string('serial_number', 100)->nullable()->unique();
            $table->text('specification')->nullable();

            $table->foreignId('owning_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('current_branch_id')->constrained('branches')->restrictOnDelete();

            // Divisi pemilik utama. Himpunan lengkapnya (satu atau dua divisi) ada di
            // tabel pivot asset_department, dan kolom ini selalu menjadi anggotanya —
            // pola yang sama dengan employees.department_id.
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->string('status', 20)->default('draft');
            $table->string('condition', 20)->default('good');

            $table->date('acquired_at')->nullable();
            // decimal, bukan float: nilai perolehan dipakai untuk penjumlahan di
            // laporan dan pembulatan biner akan terlihat sebagai selisih rupiah.
            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->date('warranty_expires_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Daftar aset selalu disaring per lokasi/kategori/divisi lalu per status,
            // dan pengingat garansi menyapu kolom tanggalnya sendiri.
            $table->index(['current_branch_id', 'status']);
            $table->index(['owning_branch_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('warranty_expires_at');
            $table->index('condition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
