<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reusable schedule template: either a fixed weekly pattern (Mon-Fri work,
        // Sat-Sun off) or a rotating cycle (e.g. 2 pagi, 2 siang, 2 off) that repeats
        // from an anchor date regardless of the weekday.
        Schema::create('schedule_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('fixed_weekly'); // fixed_weekly | rotating
            $table->unsignedSmallInteger('cycle_length')->default(7);
            $table->date('anchor_date')->nullable(); // phase reference for rotating patterns
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // One row per position in the pattern's cycle. day_index is 0-6 (Sun-Sat) for
        // weekly patterns, or 0..cycle_length-1 for rotating ones. A null shift_id
        // means that slot is a day off.
        Schema::create('schedule_pattern_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_pattern_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_index');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['schedule_pattern_id', 'day_index']);
        });

        // Binds a pattern to an employee for a period. end_date null = open-ended.
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_pattern_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });

        // The materialized daily schedule the attendance resolver will consume. One
        // row per employee per date. source=manual rows are user overrides that the
        // generator must never clobber.
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_day_off')->default(false);
            $table->string('source')->default('generated'); // generated | manual
            $table->foreignId('schedule_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_pattern_days');
        Schema::dropIfExists('schedule_patterns');
    }
};
