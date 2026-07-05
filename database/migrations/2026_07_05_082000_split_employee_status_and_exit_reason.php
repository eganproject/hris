<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('exit_reason')->nullable()->after('employment_status')->index();
            $table->date('exit_date')->nullable()->after('exit_reason')->index();
            $table->text('exit_notes')->nullable()->after('exit_date');
        });

        DB::table('employees')
            ->whereIn('employment_status', ['resigned', 'terminated', 'contract_ended', 'retired', 'deceased', 'other_inactive'])
            ->orderBy('id')
            ->each(function (object $employee): void {
                $exitReason = $employee->employment_status === 'other_inactive' ? 'other' : $employee->employment_status;

                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'exit_reason' => $exitReason,
                        'exit_date' => $employee->resigned_at,
                        'exit_notes' => $employee->resignation_reason,
                        'employment_status' => 'inactive',
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('employees')
            ->where('employment_status', 'inactive')
            ->whereNotNull('exit_reason')
            ->orderBy('id')
            ->each(function (object $employee): void {
                $employmentStatus = $employee->exit_reason === 'other' ? 'other_inactive' : $employee->exit_reason;

                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'employment_status' => $employmentStatus,
                        'resigned_at' => $employee->exit_date,
                        'resignation_reason' => $employee->exit_notes,
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['exit_reason', 'exit_date', 'exit_notes']);
        });
    }
};
