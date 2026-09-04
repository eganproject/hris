<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garis waktu perpindahan sebuah aset: diserahkan, diakui, dikembalikan, dipindah.
 *
 * Barisnya ditulis sekali dan tidak pernah disunting — karena itu tidak ada
 * updated_at, sama seperti activity_logs. Perbedaannya dengan jejak aktivitas: yang
 * ini adalah riwayat ASET yang dibaca orang gudang di halaman detailnya, bukan
 * catatan audit siapa-mengubah-kolom-apa.
 *
 * Kolom asal/tujuan sengaja tidak memakai foreign key dan menyimpan salinan nama.
 * Sebuah riwayat harus tetap terbaca setelah cabang ditutup atau karyawannya
 * dihapus; kalau tidak, justru baris paling perlu ditelusuri yang berubah jadi
 * deretan id kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('asset_assignments')->nullOnDelete();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            $table->string('type', 30);

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();

            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('from_employee_id')->nullable();
            $table->unsignedBigInteger('to_employee_id')->nullable();

            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();

            $table->string('condition', 20)->nullable();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['asset_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transactions');
    }
};
