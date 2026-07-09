<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime becomes employee-submitted and supervisor-approved. These columns carry
 * the submission (requested time window, computed minutes, reason, who submitted it
 * and which supervisor must approve) on top of the existing decision columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_approvals', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('employee_id')
                ->constrained('employees')->nullOnDelete();
            $table->time('start_time')->nullable()->after('work_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedInteger('requested_minutes')->default(0)->after('end_time');
            $table->text('reason')->nullable()->after('requested_minutes');
            $table->timestamp('requested_at')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropColumn(['start_time', 'end_time', 'requested_minutes', 'reason', 'requested_at']);
        });
    }
};
