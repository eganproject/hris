<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak notifikasi per HP, supaya HR bisa melihat HP mana yang tidak menerima
 * notifikasi dan kenapa: izin notifikasi ditolak di HP, atau kiriman terakhir
 * ditolak Firebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            // null = aplikasi versi lama yang belum melaporkan izinnya.
            $table->boolean('push_allowed')->nullable()->after('push_token_updated_at');
            $table->timestamp('last_push_at')->nullable()->after('push_allowed');
            $table->string('last_push_result', 20)->nullable()->after('last_push_at');
            $table->string('last_push_error', 255)->nullable()->after('last_push_result');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropColumn(['push_allowed', 'last_push_at', 'last_push_result', 'last_push_error']);
        });
    }
};
