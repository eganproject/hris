<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rolling log of every iclock interaction a device makes (handshake,
        // attendance push, polling, command result). Powers the device monitor
        // page. Pruned periodically to stay bounded.
        Schema::create('device_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // handshake | attlog | poll | command | data
            $table->unsignedInteger('records_count')->default(0);
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_communications');
    }
};
