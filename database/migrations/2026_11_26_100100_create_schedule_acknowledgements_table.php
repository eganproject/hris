<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atasan "menerbitkan" jadwal mingguan timnya dari aplikasi HP; tiap anggota tim
 * lalu mengonfirmasi sudah melihatnya. Satu baris per karyawan per minggu (Senin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_acknowledgements');
    }
};
