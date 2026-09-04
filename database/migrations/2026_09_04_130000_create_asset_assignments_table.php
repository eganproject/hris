<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu masa pegang: sejak aset diserahkan kepada seorang karyawan sampai ia
 * dikembalikan. Barisnya tidak pernah ditimpa — pengembalian mengisi returned_at,
 * bukan menghapus atau menyunting ulang penyerahannya, sehingga riwayat "siapa
 * memegang apa dan kapan" tetap utuh.
 *
 * acknowledged_at menyimpan pengakuan karyawan atas serah-terima. Konfirmasinya
 * wajib untuk semua kategori: sebuah aset dianggap belum beres diserahkan sampai
 * orang yang memegangnya mengakui bahwa ia memang menerimanya.
 *
 * restrictOnDelete pada aset dan karyawan, bukan cascade: baris riwayat tidak boleh
 * ikut lenyap hanya karena induknya dihapus — dan justru keberadaan baris inilah
 * yang membuat keduanya tidak bisa dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->date('expected_return_at')->nullable();
            $table->string('condition_out', 20);
            $table->string('purpose', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_note', 500)->nullable();

            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_in', 20)->nullable();
            $table->text('return_notes')->nullable();

            $table->timestamps();

            // "Assignment yang masih terbuka" adalah pertanyaan yang diajukan hampir
            // setiap layar: per aset (siapa yang memegangnya) dan per karyawan (apa
            // saja yang ia pegang, dan apakah ia boleh diproses keluar).
            $table->index(['asset_id', 'returned_at']);
            $table->index(['employee_id', 'returned_at']);
            $table->index('expected_return_at');
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
