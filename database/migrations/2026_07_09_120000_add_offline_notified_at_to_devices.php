<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether HR has already been alerted about the device's current offline
 * episode, so the offline notifier fires once per outage instead of every run.
 * Cleared when the device contacts us again (see Device::markSeen()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('offline_notified_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('offline_notified_at');
        });
    }
};
