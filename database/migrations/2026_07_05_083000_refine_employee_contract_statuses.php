<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_contracts')
            ->where('status', 'terminated')
            ->update(['status' => 'ended_early']);
    }

    public function down(): void
    {
        DB::table('employee_contracts')
            ->where('status', 'ended_early')
            ->update(['status' => 'terminated']);
    }
};
