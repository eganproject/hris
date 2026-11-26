<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token Firebase Cloud Messaging per HP, supaya server bisa mengirim notifikasi
 * yang muncul walau aplikasi sedang ditutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->string('push_token', 512)->nullable()->after('app_version');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropColumn(['push_token', 'push_token_updated_at']);
        });
    }
};
