<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_job_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['department_id', 'job_position_id']);
        });

        DB::table('job_positions')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get(['id', 'department_id', 'created_at', 'updated_at'])
            ->each(function (object $position): void {
                DB::table('department_job_position')->updateOrInsert(
                    [
                        'department_id' => $position->department_id,
                        'job_position_id' => $position->id,
                    ],
                    [
                        'is_active' => true,
                        'created_at' => $position->created_at ?? now(),
                        'updated_at' => $position->updated_at ?? now(),
                    ],
                );
            });

        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('department_job_position')
            ->where('is_active', true)
            ->orderBy('department_id')
            ->get(['department_id', 'job_position_id'])
            ->groupBy('job_position_id')
            ->each(function ($placements, int $jobPositionId): void {
                DB::table('job_positions')
                    ->where('id', $jobPositionId)
                    ->update([
                        'department_id' => $placements->first()->department_id,
                        'updated_at' => now(),
                    ]);
            });

        Schema::dropIfExists('department_job_position');
    }
};
