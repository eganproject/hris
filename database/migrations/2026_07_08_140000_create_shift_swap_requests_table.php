<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Employee-initiated schedule changes: swap shifts, hand a shift over (cover),
        // or trade a day off. Two-level approval: partner accepts, then HR approves,
        // then the change is applied as manual overrides on employee_schedules.
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->date('requester_date');
            $table->foreignId('partner_id')->constrained('employees')->cascadeOnDelete();
            $table->date('partner_date')->nullable(); // null for a one-way cover
            $table->string('type'); // swap | cover | dayoff
            $table->text('reason')->nullable();
            $table->string('status')->default('pending_partner'); // pending_partner | pending_hr | approved | rejected | cancelled
            $table->timestamp('partner_responded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
