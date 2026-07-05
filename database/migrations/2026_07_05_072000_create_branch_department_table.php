<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['branch_id', 'department_id']);
            $table->index(['branch_id', 'is_active']);
        });

        DB::table('employees')
            ->whereNotNull('branch_id')
            ->whereNotNull('department_id')
            ->select(['branch_id', 'department_id'])
            ->distinct()
            ->orderBy('branch_id')
            ->each(function (object $placement): void {
                DB::table('branch_department')->updateOrInsert(
                    [
                        'branch_id' => $placement->branch_id,
                        'department_id' => $placement->department_id,
                    ],
                    [
                        'is_primary' => false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_department');
    }
};
