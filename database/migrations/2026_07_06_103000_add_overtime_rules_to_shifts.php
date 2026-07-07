<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Overtime only starts accruing after this many minutes past the scheduled end.
            $table->unsignedSmallInteger('overtime_starts_after_minutes')->default(0)->after('early_leave_tolerance_minutes');
            // Accrued overtime below this threshold is not counted (rounded down to 0).
            $table->unsignedSmallInteger('overtime_min_minutes')->default(0)->after('overtime_starts_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['overtime_starts_after_minutes', 'overtime_min_minutes']);
        });
    }
};
