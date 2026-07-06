<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exit lifecycle is now tracked by a single source of truth: exit_reason,
     * exit_date and exit_notes. The legacy resigned_at / resignation_reason columns
     * duplicated exit_date / exit_notes and caused two-source confusion, so they are
     * removed. Any value still only present in the legacy columns is copied over first.
     */
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'resigned_at')) {
            DB::table('employees')
                ->whereNull('exit_date')
                ->whereNotNull('resigned_at')
                ->update(['exit_date' => DB::raw('resigned_at')]);
        }

        if (Schema::hasColumn('employees', 'resignation_reason')) {
            DB::table('employees')
                ->whereNull('exit_notes')
                ->whereNotNull('resignation_reason')
                ->update(['exit_notes' => DB::raw('resignation_reason')]);
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'resignation_reason')) {
                $table->dropColumn('resignation_reason');
            }

            if (Schema::hasColumn('employees', 'resigned_at')) {
                $table->dropColumn('resigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'resigned_at')) {
                $table->timestamp('resigned_at')->nullable();
            }

            if (! Schema::hasColumn('employees', 'resignation_reason')) {
                $table->text('resignation_reason')->nullable()->after('resigned_at');
            }
        });
    }
};
