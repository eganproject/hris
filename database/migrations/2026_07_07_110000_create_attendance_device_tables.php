<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registered fingerprint machines (Solution X100-C / ZKTeco). The serial
        // number is how the device identifies itself on the iclock push protocol,
        // so it doubles as the allowlist key. Only active, known SNs are accepted.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip')->nullable();
            $table->json('options')->nullable(); // push stamps / cursor bookkeeping
            $table->timestamps();
        });

        // Maps a device user PIN to an employee. device_id null = applies to any
        // device (global PIN); a device-specific row wins over a global one.
        Schema::create('employee_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('machine_user_id'); // the PIN enrolled on the machine
            $table->timestamps();

            $table->unique(['device_id', 'machine_user_id']);
            $table->index('machine_user_id');
        });

        // Every raw punch the device pushes, stored verbatim and idempotently. Rows
        // survive even when the PIN is not mapped yet (status=unmatched) so no data
        // is ever lost; they roll up into `attendances` once matched.
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('machine_user_id');
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('state')->default(0);       // ZK punch state (0=in,1=out,...)
            $table->unsignedTinyInteger('verify_mode')->default(0); // 1=fingerprint,0=password,15=face
            $table->string('status')->default('unmatched');         // matched | unmatched | ignored
            $table->string('dedup_hash')->unique();
            $table->text('raw')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
            $table->index(['status', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('employee_devices');
        Schema::dropIfExists('devices');
    }
};
