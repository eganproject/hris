<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu karyawan bisa berada di banyak divisi (semua setara). Divisi disimpan di
 * tabel pivot ini sebagai himpunan lengkap; employees.department_id tetap ada
 * sebagai "divisi jabatan" (anchor untuk penyaringan jabatan) dan selalu menjadi
 * salah satu anggota himpunan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'department_id']);
        });

        // Backfill: divisi yang sudah ada (department_id) menjadi anggota pertama.
        $now = now();
        DB::table('employees')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->select(['id', 'department_id'])
            ->chunk(500, function ($rows) use ($now): void {
                $insert = $rows->map(fn ($row) => [
                    'employee_id' => $row->id,
                    'department_id' => $row->department_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('department_employee')->insertOrIgnore($insert);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_employee');
    }
};
