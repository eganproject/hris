<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // A shift whose end time is on the next calendar day (e.g. 22:00–06:00).
            $table->boolean('crosses_midnight')->default(false)->after('end_time');
            // Grace periods before an employee is counted late / leaving early.
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0)->after('break_minutes');
            $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0)->after('late_tolerance_minutes');
        });

        // Backfill overnight flag for any existing shift where end <= start.
        DB::table('shifts')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereColumn('end_time', '<=', 'start_time')
            ->update(['crosses_midnight' => true]);
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['crosses_midnight', 'late_tolerance_minutes', 'early_leave_tolerance_minutes']);
        });
    }
};
