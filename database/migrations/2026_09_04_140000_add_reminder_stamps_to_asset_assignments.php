<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kapan sebuah serah-terima terakhir diingatkan.
 *
 * Tanpa penanda ini, penjadwal akan mengirim pengingat yang sama setiap pagi sampai
 * lonceng notifikasi berhenti dipercaya — pola yang sudah terbukti perlu pada
 * devices.offline_notified_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->timestamp('acknowledgement_reminded_at')->nullable()->after('acknowledgement_note');
            $table->timestamp('return_reminded_at')->nullable()->after('expected_return_at');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropColumn(['acknowledgement_reminded_at', 'return_reminded_at']);
        });
    }
};
