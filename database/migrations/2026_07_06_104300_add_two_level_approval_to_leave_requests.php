<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Any legacy single-step requests move to the new HR-pending state.
        DB::table('leave_requests')->where('status', 'pending')->update(['status' => 'pending_hr']);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('leave_type_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('supervisor_approved_by')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_decided_at')->nullable()->after('supervisor_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn('supervisor_decided_at');
        });
    }
};
