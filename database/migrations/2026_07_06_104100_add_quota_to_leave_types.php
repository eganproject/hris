<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // Whether this leave type draws from a yearly quota (e.g. annual leave).
            $table->boolean('counts_against_balance')->default(false)->after('is_paid');
            // Default yearly entitlement in days for quota-based types.
            $table->unsignedSmallInteger('default_quota_days')->nullable()->after('counts_against_balance');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['counts_against_balance', 'default_quota_days']);
        });
    }
};
