<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Employment status is now binary (active/inactive). Fold the retired
     * "probation" and "suspended" states into "active".
     */
    public function up(): void
    {
        DB::table('employees')
            ->whereIn('employment_status', ['probation', 'suspended'])
            ->update(['employment_status' => 'active']);
    }

    public function down(): void
    {
        // No-op: the original probation/suspended distinction cannot be recovered.
    }
};
