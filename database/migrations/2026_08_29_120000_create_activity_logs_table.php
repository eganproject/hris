<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aktivitas pengguna: siapa melakukan apa, kapan, dari mana.
 *
 * Barisnya sengaja menyimpan salinan nama pelaku dan nama objeknya, bukan hanya id.
 * Sebuah catatan audit harus tetap terbaca setelah akun penggunanya dihapus atau
 * datanya dibuang — kalau tidak, justru kejadian paling perlu ditelusuri yang malah
 * berubah jadi baris kosong.
 *
 * Tidak ada updated_at: catatan audit tidak pernah diubah setelah ditulis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();

            $table->string('module', 40);
            $table->string('event', 40);

            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();

            $table->string('description', 500);
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->nullable();

            // Halaman ini hampir selalu dibaca urut waktu, lalu disaring per pengguna
            // atau per modul — ketiganya diberi indeks komposit dengan created_at.
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
